<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Data\TimelineBucket;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Grouping\IssueFingerprinter;
use Aaix\LaravelErrorAudit\Parsing\LaravelLogParser;
use Aaix\LaravelErrorAudit\Sources\FailedJobsSource;
use Aaix\LaravelErrorAudit\Sources\LogChannelDiscovery;
use Illuminate\Support\Carbon;

class LogCollector
{
   private const HOURLY_THRESHOLD_HOURS = 48;

   public function __construct(
      private readonly LogChannelDiscovery $discovery,
      private readonly LaravelLogParser $parser,
      private readonly IssueFingerprinter $fingerprinter,
      private readonly FailedJobsSource $failedJobs,
   ) {}

   public function collect(
      Carbon $since,
      Carbon $until,
      LogLevelEnum $minimumLevel,
      int $samplesPerIssue = 2,
   ): CollectedLogs {
      $hourly = $since->diffInHours($until) <= self::HOURLY_THRESHOLD_HOURS;
      $timeline = $this->buildTimeline($since, $until, $hourly);

      /** @var array<string, IssueGroup> $groups */
      $groups = [];
      $channels = [];
      $errorCount = 0;
      $warningCount = 0;

      foreach ($this->discovery->discover() as $file) {
         if (! $this->mayContainEntries($file->path, $since)) {
            continue;
         }

         foreach ($this->parser->parse($file, $since, $until, $minimumLevel) as $entry) {
            $this->ingest($entry, $hourly, $samplesPerIssue, $groups, $timeline, $channels, $errorCount, $warningCount);
         }
      }

      foreach ($this->failedJobs->entries($since, $until) as $entry) {
         if ($entry->level->isAtLeast($minimumLevel)) {
            $this->ingest($entry, $hourly, $samplesPerIssue, $groups, $timeline, $channels, $errorCount, $warningCount);
         }
      }

      return new CollectedLogs(
         groups: $groups,
         timeline: array_values($timeline),
         channels: array_keys($channels),
         errorCount: $errorCount,
         warningCount: $warningCount,
      );
   }

   /**
    * @param  array<string, IssueGroup>  $groups
    * @param  array<string, TimelineBucket>  $timeline
    * @param  array<string, true>  $channels
    */
   private function ingest(
      LogEntry $entry,
      bool $hourly,
      int $samplesPerIssue,
      array &$groups,
      array &$timeline,
      array &$channels,
      int &$errorCount,
      int &$warningCount,
   ): void {
      $channels[$entry->channel] = true;
      $bucketKey = $this->bucketKey($entry->loggedAt, $hourly);

      if ($entry->level->isError()) {
         $errorCount++;
      } else {
         $warningCount++;
      }

      if (isset($timeline[$bucketKey])) {
         if ($entry->level->isError()) {
            $timeline[$bucketKey]->errors++;
         } else {
            $timeline[$bucketKey]->warnings++;
         }
      }

      $fingerprint = $this->fingerprinter->fingerprint($entry);

      $groups[$fingerprint] ??= new IssueGroup(
         fingerprint: $fingerprint,
         level: $entry->level,
         exceptionClass: $entry->exceptionClass,
         normalizedMessage: $this->fingerprinter->signature($entry->message),
         sampleLimit: $samplesPerIssue,
      );

      $groups[$fingerprint]->add($entry, $bucketKey);
   }

   /**
    * A file last written before the window opened cannot hold entries inside it.
    */
   private function mayContainEntries(string $path, Carbon $since): bool
   {
      $modifiedAt = @filemtime($path);

      return $modifiedAt === false || $modifiedAt >= $since->getTimestamp();
   }

   /**
    * @return array<string, TimelineBucket>
    */
   private function buildTimeline(Carbon $since, Carbon $until, bool $hourly): array
   {
      $buckets = [];
      $cursor = $hourly ? $since->copy()->startOfHour() : $since->copy()->startOfDay();

      while ($cursor->lessThanOrEqualTo($until)) {
         $key = $this->bucketKey($cursor, $hourly);

         $buckets[$key] = new TimelineBucket(
            key: $key,
            startsAt: $cursor->copy(),
            label: $hourly ? $cursor->format('H') : $cursor->format('d.m.'),
         );

         $cursor = $hourly ? $cursor->addHour() : $cursor->addDay();
      }

      return $buckets;
   }

   private function bucketKey(Carbon $moment, bool $hourly): string
   {
      return $hourly ? $moment->format('Y-m-d H') : $moment->format('Y-m-d');
   }
}
