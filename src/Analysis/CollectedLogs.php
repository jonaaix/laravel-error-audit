<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\TimelineBucket;

final class CollectedLogs
{
   /**
    * @param  array<string, IssueGroup>  $groups
    * @param  list<TimelineBucket>  $timeline
    * @param  list<string>  $channels
    */
   public function __construct(
      public readonly array $groups,
      public readonly array $timeline,
      public readonly array $channels,
      public readonly int $errorCount,
      public readonly int $warningCount,
   ) {}

   /**
    * @return list<IssueGroup>
    */
   public function groupsByFrequency(): array
   {
      $groups = array_values($this->groups);

      usort($groups, fn (IssueGroup $a, IssueGroup $b): int => $b->count() <=> $a->count());

      return $groups;
   }
}
