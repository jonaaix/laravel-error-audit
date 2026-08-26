<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Illuminate\Support\Carbon;

final class IssueGroup
{
   private const TITLE_LENGTH = 100;

   /** @var list<LogEntry> */
   private array $samples = [];

   /** @var array<string, int> */
   private array $bucketCounts = [];

   /** @var array<string, true> */
   private array $channels = [];

   private int $count = 0;

   private ?Carbon $firstSeen = null;

   private ?Carbon $lastSeen = null;

   public function __construct(
      public readonly string $fingerprint,
      public readonly LogLevelEnum $level,
      public readonly ?string $exceptionClass,
      public readonly string $normalizedMessage,
      private readonly int $sampleLimit = 2,
   ) {}

   public function add(LogEntry $entry, string $bucketKey): void
   {
      $this->count++;
      $this->channels[$entry->channel] = true;
      $this->bucketCounts[$bucketKey] = ($this->bucketCounts[$bucketKey] ?? 0) + 1;

      if ($this->firstSeen === null || $entry->loggedAt->lessThan($this->firstSeen)) {
         $this->firstSeen = $entry->loggedAt->copy();
      }

      if ($this->lastSeen === null || $entry->loggedAt->greaterThan($this->lastSeen)) {
         $this->lastSeen = $entry->loggedAt->copy();
      }

      $this->collectSample($entry);
   }

   /**
    * Keeps the longest distinct samples seen across every occurrence. A longer
    * message carries more context — nested exceptions, SQL, the full driver
    * complaint — which is exactly what the model needs to judge the cause. When
    * the slots are full, the shortest held sample makes way for a longer one.
    */
   private function collectSample(LogEntry $entry): void
   {
      foreach ($this->samples as $existing) {
         if ($existing->message === $entry->message) {
            return;
         }
      }

      if (count($this->samples) < $this->sampleLimit) {
         $this->samples[] = $entry;

         return;
      }

      $shortest = 0;

      foreach ($this->samples as $index => $existing) {
         if (mb_strlen($existing->message) < mb_strlen($this->samples[$shortest]->message)) {
            $shortest = $index;
         }
      }

      if (mb_strlen($entry->message) > mb_strlen($this->samples[$shortest]->message)) {
         $this->samples[$shortest] = $entry;
      }
   }

   public function count(): int
   {
      return $this->count;
   }

   /**
    * @return list<LogEntry>
    */
   public function samples(): array
   {
      return $this->samples;
   }

   /**
    * @return list<string>
    */
   public function channels(): array
   {
      return array_keys($this->channels);
   }

   public function firstSeen(): Carbon
   {
      return $this->firstSeen ?? Carbon::now();
   }

   public function lastSeen(): Carbon
   {
      return $this->lastSeen ?? Carbon::now();
   }

   public function countInBucket(string $bucketKey): int
   {
      return $this->bucketCounts[$bucketKey] ?? 0;
   }

   public function codePath(): ?string
   {
      foreach ($this->samples as $sample) {
         if ($sample->appFrame !== null) {
            return $sample->appFrame;
         }
      }

      return null;
   }

   /**
    * Anything logged without an exception — every Log::warning() — has only its
    * message to identify it. The normalised form is what carries into the
    * report: it is the very text the group was formed on, and its placeholders
    * already stand in for the values a raw line would expose.
    */
   public function title(): string
   {
      if ($this->exceptionClass !== null) {
         return class_basename($this->exceptionClass);
      }

      $message = trim($this->normalizedMessage);

      if ($message === '') {
         return $this->level->label();
      }

      return mb_strlen($message) > self::TITLE_LENGTH
         ? rtrim(mb_substr($message, 0, self::TITLE_LENGTH)).'…'
         : $message;
   }
}
