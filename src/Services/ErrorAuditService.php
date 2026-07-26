<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Services;

use Aaix\LaravelErrorAudit\Analysis\AssessmentStore;
use Aaix\LaravelErrorAudit\Analysis\CollectedLogs;
use Aaix\LaravelErrorAudit\Charts\TimelineSeriesBuilder;
use Aaix\LaravelErrorAudit\Contracts\ChartRenderer;
use Aaix\LaravelErrorAudit\Analysis\IssueAnalyzer;
use Aaix\LaravelErrorAudit\Analysis\LogCollector;
use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\ErrorAudit;
use Illuminate\Support\Carbon;

class ErrorAuditService
{
   public function __construct(
      private readonly LogCollector $collector,
      private readonly IssueAnalyzer $analyzer,
      private readonly AssessmentStore $store,
      private readonly ErrorAudit $errorAudit,
      private readonly ChartRenderer $chartRenderer,
      private readonly TimelineSeriesBuilder $seriesBuilder,
   ) {}

   public function generate(?Carbon $since = null, ?Carbon $until = null, bool $refresh = false): AuditReport
   {
      $until ??= Carbon::now();
      $since ??= $until->copy()->sub($this->period());

      $collected = $this->collect($since, $until);
      $previous = $this->previousPeriodTotals($since, $until);
      $analysis = $this->analyzer->analyse(
         $collected->groupsByFrequency(),
         $this->describePeriod($since, $until),
         $refresh,
      );

      return new AuditReport(
         applicationName: (string) config('app.name', 'Laravel'),
         periodStart: $since,
         periodEnd: $until,
         issues: $analysis->issues,
         timeline: $collected->timeline,
         channels: $collected->channels,
         errorCount: $collected->errorCount,
         warningCount: $collected->warningCount,
         previousErrorCount: $previous['error'],
         previousWarningCount: $previous['warning'],
         analysedIssueCount: $analysis->analysedCount,
         analysisCostUsd: $analysis->costUsd,
         analysisModel: $analysis->model,
         discardedEntryCount: 0,
         chartPng: $this->chartRenderer->render(
            $collected->timeline,
            $this->seriesBuilder->build($collected->groups, $collected->timeline),
         ),
      );
   }

   /**
    * Count the immediately preceding window of the same length, so a delta
    * always compares like with like. Reading it fresh on every run is what
    * keeps the figure honest when the analysed period changes.
    *
    * @return array{error: ?int, warning: ?int}
    */
   private function previousPeriodTotals(Carbon $since, Carbon $until): array
   {
      $length = $until->diffInSeconds($since);

      if ($length <= 0) {
         return ['error' => null, 'warning' => null];
      }

      $previous = $this->collect($since->copy()->subSeconds((int) $length), $since->copy()->subSecond());

      return ['error' => $previous->errorCount, 'warning' => $previous->warningCount];
   }

   public function collect(Carbon $since, Carbon $until): CollectedLogs
   {
      return $this->collector->collect(
         $since,
         $until,
         $this->minimumLevel(),
         (int) $this->errorAudit->value('ai.samples_per_issue', 2),
      );
   }

   public function period(): \DateInterval
   {
      $period = (string) $this->errorAudit->value('period', '24 hours');

      return \DateInterval::createFromDateString($period) ?: \DateInterval::createFromDateString('24 hours');
   }

   public function minimumLevel(): LogLevelEnum
   {
      return $this->errorAudit->minimumLevel();
   }

   public function describePeriod(Carbon $since, Carbon $until): string
   {
      return $since->toDateTimeString().' to '.$until->toDateTimeString();
   }
}
