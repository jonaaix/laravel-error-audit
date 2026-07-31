<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Analysis\AnalysisBudget;
use Aaix\LaravelErrorAudit\Analysis\DailyCostLedger;
use Aaix\LaravelErrorAudit\Analysis\IssueAnalyzer;
use Aaix\LaravelErrorAudit\Contracts\AuditProgress;
use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;
use Aaix\LaravelErrorAudit\Tests\ErrorAuditReportFactory;
use Illuminate\Support\Carbon;

it('accumulates the spend per calendar day', function (): void {
   $ledger = app(DailyCostLedger::class);

   $ledger->add(0.0021);
   $ledger->add(0.0009);

   expect(round($ledger->spentToday(), 4))->toBe(0.003);
});

it('starts a fresh ledger on the next day', function (): void {
   Carbon::setTestNow('2026-07-31 09:00:00');
   app(DailyCostLedger::class)->add(0.98);

   Carbon::setTestNow('2026-08-01 07:00:00');
   expect(app(DailyCostLedger::class)->spentToday())->toBe(0.0);

   Carbon::setTestNow();
});

it('treats a null cap as no cap at all', function (): void {
   $budget = new AnalysisBudget(maxIssues: null, maxInputTokens: null);

   $budget->consume(500000);

   expect($budget->allows(500000))->toBeTrue();
});

it('stops asking once the daily cost limit is spent', function (): void {
   config()->set('error-audit.ai.enabled', true);
   config()->set('error-audit.ai.max_daily_cost_usd', 1.0);

   app(DailyCostLedger::class)->add(1.0);

   $progress = new class implements AuditProgress
   {
      public array $outcomes = [];

      public array $details = [];

      public function phase(string $description): void {}

      public function detail(string $description): void
      {
         $this->details[] = $description;
      }

      public function issue(string $title, int $occurrences, AnalysisOutcomeEnum $outcome, ?float $costUsd = null): void
      {
         $this->outcomes[] = $outcome;
      }
   };

   $result = app(IssueAnalyzer::class)->analyse(
      [ErrorAuditReportFactory::group('cl1', 'RedisException', 3)],
      'the last 24 hours',
      progress: $progress,
   );

   expect($progress->outcomes)->toBe([AnalysisOutcomeEnum::SkippedCost])
      ->and($result->analysedCount)->toBe(0)
      ->and($result->issues[0]->assessment)->toBeNull()
      ->and($progress->details)->toContain('daily analysis spend: $1.0000 of $1.00');
});
