<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Contracts;

use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;

/**
 * Receives what the audit is doing while it runs. A synchronous console run
 * renders this live; the queued job runs against the null implementation.
 */
interface AuditProgress
{
   /**
    * A new stage of the run: collecting, analysing, rendering the chart.
    */
   public function phase(string $description): void;

   /**
    * A secondary line under the current phase — counts, notes, outcomes that
    * concern the phase as a whole.
    */
   public function detail(string $description): void;

   /**
    * One issue passed through the analysis, with what happened to it.
    */
   public function issue(string $title, int $occurrences, AnalysisOutcomeEnum $outcome, ?float $costUsd = null): void;
}
