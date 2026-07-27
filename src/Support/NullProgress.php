<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Support;

use Aaix\LaravelErrorAudit\Contracts\AuditProgress;
use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;

final class NullProgress implements AuditProgress
{
   public function phase(string $description): void {}

   public function detail(string $description): void {}

   public function issue(string $title, int $occurrences, AnalysisOutcomeEnum $outcome, ?float $costUsd = null): void {}
}
