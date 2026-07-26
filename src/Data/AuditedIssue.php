<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

final class AuditedIssue
{
   public function __construct(
      public readonly IssueGroup $group,
      public readonly ?IssueAssessment $assessment,
      public readonly bool $isNew,
      public readonly ?int $previousCount,
      public readonly ?int $daysOpen,
   ) {}

   public function deltaPercent(): ?int
   {
      if ($this->previousCount === null || $this->previousCount === 0) {
         return null;
      }

      return (int) round((($this->group->count() - $this->previousCount) / $this->previousCount) * 100);
   }
}
