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

      $progress->phase('Analysing issues');
      $analysis = $this->analyzer->analyse(
         $collected->groupsByFrequency(),
         $this->describePeriod($since, $until),
         $refresh,
         $progress,
      );

      if ($analysis->inputTokens > 0) {
         $progress->detail($analysis->maxInputTokens > 0
            ? sprintf('~%s of %s input tokens used (estimated)', number_format($analysis->inputTokens), number_format($analysis->maxInputTokens))
            : sprintf('~%s input tokens used (estimated, no token limit)', number_format($analysis->inputTokens)));
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
         analysedIssueCount: $analysis->analysedCount,
         analysisCostUsd: $analysis->costUsd,
         analysisModel: $analysis->model,
         discardedEntryCount: 0,
         chartPng: $chartPng,
         analysisInputTokens: $analysis->inputTokens,
         analysisMaxInputTokens: $analysis->maxInputTokens,
      );
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
