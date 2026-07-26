<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Data\AuditedIssue;

final class AnalysisResult
{
   /**
    * @param  list<AuditedIssue>  $issues
    */
   public function __construct(
      public readonly array $issues,
      public readonly int $analysedCount,
      public readonly float $costUsd,
      public readonly ?string $model,
   ) {}
}
