<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

final class AuditedIssue
{
   /**
    * "New" and the previous count are relative to the preceding time window of
    * the same length as the analysed one — never to earlier runs.
    */
   public function __construct(
      public readonly IssueGroup $group,
      public readonly ?IssueAssessment $assessment,
      public readonly bool $isNew,
      public readonly ?int $previousCount,
   ) {}

   public function deltaPercent(): ?int
   {
      if ($this->previousCount === null || $this->previousCount === 0) {
         return null;
      }

      return (int) round((($this->group->count() - $this->previousCount) / $this->previousCount) * 100);
   }
}
