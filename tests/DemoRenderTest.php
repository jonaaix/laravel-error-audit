<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Charts\GdChartRenderer;
use Aaix\LaravelErrorAudit\Charts\TimelineSeriesBuilder;
use Aaix\LaravelErrorAudit\Data\AuditedIssue;
use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\Data\IssueAssessment;
use Aaix\LaravelErrorAudit\Enums\IssueCategoryEnum;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;
use Aaix\LaravelErrorAudit\Mail\ErrorAuditMail;
use Aaix\LaravelErrorAudit\Tests\ErrorAuditReportFactory;

/**
 * Renders demo.html in the project root — a browsable preview of the report
 * email with every visual state on display. Running the suite keeps it
 * current, so the committed file never drifts from the templates.
 */
it('renders the demo report to demo.html', function (): void {
   $assessment = fn (UrgencyEnum $urgency, IssueCategoryEnum $category, string $title, string $cause, string $action) => new IssueAssessment($urgency, $category, $title, $cause, $action);

   $groups = [
      'a1' => ErrorAuditReportFactory::group('a1', 'Illuminate\Database\QueryException', 24, LogLevelEnum::Error, 'daily'),
      'a2' => ErrorAuditReportFactory::group('a2', 'App\Exceptions\PaymentDeclined', 8, LogLevelEnum::Error, 'daily'),
      'a3' => ErrorAuditReportFactory::group('a3', 'ErrorException', 19, LogLevelEnum::Warning, 'daily'),
      'q1' => ErrorAuditReportFactory::group('q1', 'RuntimeException', 17, LogLevelEnum::Error, 'failed-jobs'),
      'q2' => ErrorAuditReportFactory::group('q2', 'App\Exceptions\ExportTimeout', 4, LogLevelEnum::Error, 'failed-jobs'),
      'n1' => ErrorAuditReportFactory::group('n1', 'UpstreamTimeout', 63, LogLevelEnum::Warning, 'nginx'),
   ];

   $issues = [
      new AuditedIssue($groups['a1'], $assessment(
         UrgencyEnum::Critical, IssueCategoryEnum::Bug,
         'Nightly reconciliation writes into a missing table',
         'A migration adding the reconciliation table was never applied on this environment.',
         'Run the pending migration, then re-queue the failed batch from the Horizon dashboard.',
      ), true, 12),
      new AuditedIssue($groups['a2'], $assessment(
         UrgencyEnum::Medium, IssueCategoryEnum::Integration,
         'Payment provider declines cards issued outside the EU',
         'The provider profile is configured with an EU-only acquiring route.',
         'Confirm the intended market coverage with the provider dashboard settings.',
      ), false, null),
      new AuditedIssue($groups['a3'], $assessment(
         UrgencyEnum::Low, IssueCategoryEnum::Deprecation,
         'Deprecated null coalescing on a typed property',
         'A dependency calls a setter that PHP 8.4 flags as deprecated.',
         'Update the aaix/legacy-import package to ^3.2 where this is fixed.',
      ), false, null),
      new AuditedIssue($groups['q1'], $assessment(
         UrgencyEnum::High, IssueCategoryEnum::Infrastructure,
         'Order sync jobs exhaust retries against the ERP endpoint',
         'The ERP API rejects the sync payload since the last schema change.',
         'Replay one failed job with horizon:forget after deploying the new mapping.',
      ), true, null),
      new AuditedIssue($groups['q2'], null, false, null),
      new AuditedIssue($groups['n1'], $assessment(
         UrgencyEnum::Noise, IssueCategoryEnum::Noise,
         'Sporadic upstream timeouts from the image resizer',
         'The resizer cold-starts under low traffic and misses the 5s proxy timeout.',
         'Raise proxy_read_timeout for the resizer location or keep one instance warm.',
      ), false, 71),
   ];

   $base = ErrorAuditReportFactory::report();

   $report = new AuditReport(
      applicationName: 'Acme IMS',
      periodStart: $base->periodStart,
      periodEnd: $base->periodEnd,
      issues: $issues,
      timeline: $base->timeline,
      channels: ['daily', 'nginx', 'failed-jobs'],
      errorCount: 53,
      warningCount: 82,
      previousErrorCount: 31,
      previousWarningCount: 97,
      analysedIssueCount: 5,
      analysisCostUsd: 0.0087,
      analysisModel: 'gemini-3.5-flash-lite',
      discardedEntryCount: 0,
      chartPng: app(GdChartRenderer::class)->render(
         $base->timeline,
         app(TimelineSeriesBuilder::class)->build($groups, $base->timeline),
      ),
   );

   $html = (new ErrorAuditMail($report))->render();

   file_put_contents(__DIR__.'/../demo.html', $html);

   expect($html)->toContain('audit-channel-divider')
      ->and($html)->toContain('audit-badge-queue');
});
