<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Services;

use Aaix\LaravelErrorAudit\Analysis\CollectedLogs;
use Aaix\LaravelErrorAudit\Charts\TimelineSeriesBuilder;
use Aaix\LaravelErrorAudit\Contracts\AuditProgress;
use Aaix\LaravelErrorAudit\Contracts\ChartRenderer;
use Aaix\LaravelErrorAudit\Support\NullProgress;
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
      private readonly ErrorAudit $errorAudit,
      private readonly ChartRenderer $chartRenderer,
      private readonly TimelineSeriesBuilder $seriesBuilder,
   ) {}

   public function generate(
      ?Carbon $since = null,
      ?Carbon $until = null,
      bool $refresh = false,
      ?AuditProgress $progress = null,
   ): AuditReport {
      $progress ??= new NullProgress;
      $until ??= Carbon::now();
      $since ??= $until->copy()->sub($this->period());

      $progress->phase('Collecting entries — log channels and failed jobs');
      $collected = $this->collect($since, $until);
      $progress->detail(sprintf(
         '%s errors and %s warnings on %s → %d distinct issue types',
         number_format($collected->errorCount),
         number_format($collected->warningCount),
         $collected->channels !== [] ? implode(', ', $collected->channels) : 'no channels',
         count($collected->groups),
      ));

      $progress->phase('Reading the preceding period for the change rates');
      $previous = $this->previousPeriod($since, $until);
      $progress->detail(sprintf(
         'previously %s errors and %s warnings → %d issue types',
         number_format($previous?->errorCount ?? 0),
         number_format($previous?->warningCount ?? 0),
         $previous !== null ? count($previous->groups) : 0,
      ));

      $progress->phase('Analysing issues');
      $analysis = $this->analyzer->analyse(
         $collected->groupsByFrequency(),
         $this->describePeriod($since, $until),
         $refresh,
         $progress,
         $previous?->groups ?? [],
      );

      if ($analysis->inputTokens > 0) {
         $progress->detail(sprintf(
            '~%s of %s input tokens used (estimated)',
            number_format($analysis->inputTokens),
            number_format($analysis->maxInputTokens),
         ));
      }

      $progress->phase('Rendering the timeline chart');
      $chartPng = $this->chartRenderer->render(
         $collected->timeline,
         $this->seriesBuilder->build($collected->groups, $collected->timeline),
      );
      $progress->detail($chartPng !== null ? 'rendered with GD' : 'skipped — ext-gd not available');

      return new AuditReport(
         applicationName: (string) config('app.name', 'Laravel'),
         periodStart: $since,
         periodEnd: $until,
         issues: $analysis->issues,
         timeline: $collected->timeline,
         channels: $collected->channels,
         errorCount: $collected->errorCount,
         warningCount: $collected->warningCount,
         previousErrorCount: $previous?->errorCount,
         previousWarningCount: $previous?->warningCount,
         analysedIssueCount: $analysis->analysedCount,
         analysisCostUsd: $analysis->costUsd,
         analysisModel: $analysis->model,
         discardedEntryCount: 0,
         chartPng: $chartPng,
         analysisInputTokens: $analysis->inputTokens,
         analysisMaxInputTokens: $analysis->maxInputTokens,
      );
   }

   /**
    * Collect the immediately preceding window of the same length, so every
    * delta — totals, per-issue counts, "new" — compares like with like.
    * Reading it fresh from the logs on every run is what makes the report a
    * pure function of the window: the same period always yields the same
    * report.
    */
   private function previousPeriod(Carbon $since, Carbon $until): ?CollectedLogs
   {
      $length = $until->diffInSeconds($since);

      if ($length <= 0) {
         return null;
      }

      return $this->collect($since->copy()->subSeconds((int) $length), $since->copy()->subSecond());
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
