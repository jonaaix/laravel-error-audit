<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;
use Aaix\LaravelErrorAudit\Sources\FailedJobsSource;
use Illuminate\Support\Carbon;

final class AuditReport
{
   /**
    * @param  list<AuditedIssue>  $issues
    * @param  list<TimelineBucket>  $timeline
    * @param  list<string>  $channels
    */
   public function __construct(
      public readonly string $applicationName,
      public readonly Carbon $periodStart,
      public readonly Carbon $periodEnd,
      public readonly array $issues,
      public readonly array $timeline,
      public readonly array $channels,
      public readonly int $errorCount,
      public readonly int $warningCount,
      public readonly ?int $previousErrorCount,
      public readonly ?int $previousWarningCount,
      public readonly int $analysedIssueCount,
      public readonly float $analysisCostUsd,
      public readonly ?string $analysisModel,
      public readonly int $discardedEntryCount,
      public readonly ?string $chartPng = null,
      public readonly int $analysisInputTokens = 0,
      public readonly int $analysisMaxInputTokens = 0,
   ) {}

   public function periodDays(): int
   {
      return max(1, (int) round($this->periodStart->diffInHours($this->periodEnd) / 24));
   }

   public function periodLabel(): string
   {
      return trans_choice('{1}:count day|[2,*]:count days', $this->periodDays(), [
         'count' => $this->periodDays(),
      ]);
   }

   /**
    * Naming the channel on every card is noise when there is only one; it is
    * essential the moment a report spans several.
    */
   public function usesMultipleChannels(): bool
   {
      return count($this->channels) > 1;
   }

   /**
    * Within a single day the date adds nothing the header did not already say.
    */
   public function formatTimestamp(Carbon $moment): string
   {
      return $this->periodDays() <= 1
         ? $moment->format('H:i')
         : $moment->format('d.m. H:i');
   }

   public function errorTypeCount(): int
   {
      return count(array_filter($this->issues, fn (AuditedIssue $issue): bool => $issue->group->level->isError()));
   }

   public function warningTypeCount(): int
   {
      return count(array_filter($this->issues, fn (AuditedIssue $issue): bool => ! $issue->group->level->isError()));
   }

   public function hasChart(): bool
   {
      return $this->chartPng !== null;
   }

   public function chartDataUri(): ?string
   {
      return $this->chartPng !== null
         ? 'data:image/png;base64,'.base64_encode($this->chartPng)
         : null;
   }

   /**
    * Issues grouped for display: one section per channel so the reader knows
    * where they are while scrolling — now the app log, now the queue, now
    * nginx. The urgency order is kept within each section, and failed jobs
    * always form the last section. A group seen on several channels sections
    * under the first channel it appeared on.
    *
    * @return array<string, list<AuditedIssue>>
    */
   public function issuesByChannel(): array
   {
      $sections = [];

      foreach ($this->issues as $issue) {
         $channel = $issue->group->channels()[0] ?? 'log';
         $sections[$channel][] = $issue;
      }

      if (isset($sections[FailedJobsSource::CHANNEL])) {
         $queue = $sections[FailedJobsSource::CHANNEL];
         unset($sections[FailedJobsSource::CHANNEL]);
         $sections[FailedJobsSource::CHANNEL] = $queue;
      }

      return $sections;
   }

   public function issueTypeCount(): int
   {
      return count($this->issues);
   }

   public function newIssueTypeCount(): int
   {
      return count(array_filter($this->issues, fn (AuditedIssue $issue): bool => $issue->isNew));
   }

   public function isEmpty(): bool
   {
      return $this->issues === [];
   }

   public function highestUrgency(): UrgencyEnum
   {
      $highest = UrgencyEnum::Noise;

      foreach ($this->issues as $issue) {
         if ($issue->assessment === null) {
            continue;
         }

         if ($issue->assessment->urgency->rank() < $highest->rank()) {
            $highest = $issue->assessment->urgency;
         }
      }

      return $highest;
   }

   public function statusWord(): string
   {
      if ($this->isEmpty()) {
         return __('quiet');
      }

      return match ($this->highestUrgency()) {
         UrgencyEnum::Critical, UrgencyEnum::High => __('critical'),
         UrgencyEnum::Medium => __('notable'),
         default => __('quiet'),
      };
   }

   public function statusColor(): string
   {
      if ($this->isEmpty()) {
         return '#6B7280';
      }

      return match ($this->highestUrgency()) {
         UrgencyEnum::Critical, UrgencyEnum::High => '#DC2626',
         UrgencyEnum::Medium => '#B45309',
         default => '#6B7280',
      };
   }

   public function peakBucket(): ?TimelineBucket
   {
      $peak = null;

      foreach ($this->timeline as $bucket) {
         if ($peak === null || $bucket->total() > $peak->total()) {
            $peak = $bucket;
         }
      }

      return $peak?->total() > 0 ? $peak : null;
   }

   public function errorDeltaPercent(): ?int
   {
      return $this->percentDelta($this->errorCount, $this->previousErrorCount);
   }

   public function warningDeltaPercent(): ?int
   {
      return $this->percentDelta($this->warningCount, $this->previousWarningCount);
   }

   private function percentDelta(int $current, ?int $previous): ?int
   {
      if ($previous === null || $previous === 0) {
         return null;
      }

      return (int) round((($current - $previous) / $previous) * 100);
   }
}
