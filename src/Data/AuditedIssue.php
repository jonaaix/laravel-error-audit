<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;

final class AuditedIssue
{
   /**
    * The outcome records why an issue does or does not carry an assessment, so
    * the report can name the actual reason instead of guessing at one.
    */
   public function __construct(
      public readonly IssueGroup $group,
      public readonly ?IssueAssessment $assessment,
      public readonly AnalysisOutcomeEnum $outcome,
   ) {}
}
