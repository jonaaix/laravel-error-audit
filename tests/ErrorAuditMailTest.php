<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;
use Aaix\LaravelErrorAudit\Mail\ErrorAuditMail;
use Aaix\LaravelErrorAudit\Tests\ErrorAuditReportFactory;

function renderAudit(bool $withAssessments = true): string
{
   return (new ErrorAuditMail(ErrorAuditReportFactory::report($withAssessments)))->render();
}

it('opens with the package name and the analysed period', function (): void {
   $html = renderAudit();

   expect($html)->toContain('Laravel Error Audit')
      ->and($html)->toContain('23.07.2026')
      ->and($html)->toContain('1 day')
      ->and($html)->toContain('Acme IMS')
      ->and($html)->toContain('22.07.2026 07:00');
});

it('summarises errors and warnings with their own type counts', function (): void {
   $html = renderAudit();

   expect($html)->toContain('526')
      ->and($html)->toContain('Errors')
      ->and($html)->toContain('3 types')
      ->and($html)->toContain('1.444')
      ->and($html)->toContain('Warnings')
      ->and($html)->toContain('2 types');
});

it('separates the two summaries by colour as well as by wording', function (): void {
   $html = renderAudit();

   expect($html)->toContain('#DC2626')
      ->and($html)->toContain('#B45309');
});

it('keeps the header, summary and issues in that order', function (): void {
   $html = renderAudit();

   expect(strpos($html, 'Laravel Error Audit'))->toBeLessThan(strpos($html, 'audit-summary-count'))
      ->and(strpos($html, 'audit-summary-count'))->toBeLessThan(strpos($html, 'QueryException'));
});

it('embeds the rendered chart as a picture', function (): void {
   $report = ErrorAuditReportFactory::report(chartPng: 'fake-png-bytes');
   $html = (new ErrorAuditMail($report))->render();

   expect($html)->toContain('<img')
      ->and($html)->toContain(base64_encode('fake-png-bytes'));
});

it('leaves the chart out when none could be rendered', function (): void {
   expect(renderAudit())->not->toContain('<img');
});

it('sorts issues by urgency', function (): void {
   $html = renderAudit();

   expect(strpos($html, 'QueryException'))->toBeLessThan(strpos($html, 'NotFoundHttpException'));
});

it('reports the analysis cost and coverage', function (): void {
   expect(renderAudit())->toContain('5 of 5 issue types analysed by AI')
      ->and(renderAudit())->toContain('0.0041');
});

it('inlines the packaged theme instead of the framework default', function (): void {
   expect(renderAudit())->toContain('audit-summary');
});

it('leads the subject with the Audit keyword and date, then the two counts, then the app', function (): void {
   $envelope = (new ErrorAuditMail(ErrorAuditReportFactory::report()))->envelope();

   expect($envelope->subject)->toBe('Audit 23.07. — 526 ERRORS · 1.444 WARNINGS — Acme IMS');
});

it('states plainly when an issue was not analysed', function (): void {
   expect(renderAudit(withAssessments: false))->toContain('beyond the analysis budget');
});

it('names the reason an issue went unanalysed instead of always blaming the budget', function (AnalysisOutcomeEnum $outcome, string $expected): void {
   $html = (new ErrorAuditMail(unanalysedReport($outcome)))->render();

   expect($html)->toContain($expected);

   if ($outcome !== AnalysisOutcomeEnum::SkippedBudget) {
      expect($html)->not->toContain('beyond the analysis budget');
   }
})->with([
   'ai switched off' => [AnalysisOutcomeEnum::Disabled, 'AI analysis is switched off'],
   'daily cost limit' => [AnalysisOutcomeEnum::SkippedCost, 'daily analysis cost limit'],
   'provider call failed' => [AnalysisOutcomeEnum::Failed, 'request to the AI provider failed'],
   'budget exhausted' => [AnalysisOutcomeEnum::SkippedBudget, 'beyond the analysis budget'],
]);

it('ships a plain text alternative without markup', function (): void {
   $report = ErrorAuditReportFactory::report();
   $content = (new ErrorAuditMail($report))->content();

   expect($content->text)->toBe('error-audit::mail.report-text');

   $text = view($content->text, ['report' => $report])->render();

   expect($text)->toContain('Acme IMS')
      ->and($text)->toContain('QueryException')
      ->and($text)->not->toContain('<table');
});



function unanalysedReport(AnalysisOutcomeEnum $outcome): \Aaix\LaravelErrorAudit\Data\AuditReport
{
   $base = ErrorAuditReportFactory::report(withAssessments: false);

   return new \Aaix\LaravelErrorAudit\Data\AuditReport(
      applicationName: $base->applicationName,
      periodStart: $base->periodStart,
      periodEnd: $base->periodEnd,
      issues: array_map(fn (\Aaix\LaravelErrorAudit\Data\AuditedIssue $issue) => new \Aaix\LaravelErrorAudit\Data\AuditedIssue(
         group: $issue->group,
         assessment: null,
         outcome: $outcome,
      ), $base->issues),
      timeline: $base->timeline,
      channels: $base->channels,
      errorCount: $base->errorCount,
      warningCount: $base->warningCount,
      analysedIssueCount: 0,
      analysisCostUsd: 0.0,
      analysisModel: null,
      discardedEntryCount: 0,
   );
}

function multiChannelReport(): \Aaix\LaravelErrorAudit\Data\AuditReport
{
   $issue = fn (string $fingerprint, string $class, string $channel) => new \Aaix\LaravelErrorAudit\Data\AuditedIssue(
      group: ErrorAuditReportFactory::group($fingerprint, $class, 3, channel: $channel),
      assessment: null,
      outcome: \Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum::SkippedBudget,
   );

   return new \Aaix\LaravelErrorAudit\Data\AuditReport(
      applicationName: 'Acme IMS',
      periodStart: \Illuminate\Support\Carbon::parse('2026-07-22 07:00:00'),
      periodEnd: \Illuminate\Support\Carbon::parse('2026-07-23 07:00:00'),
      issues: [
         $issue('q1', 'App\Jobs\SyncFailure', 'failed-jobs'),
         $issue('a1', 'Illuminate\Database\QueryException', 'daily'),
         $issue('n1', 'UpstreamTimeout', 'nginx'),
      ],
      timeline: [],
      channels: ['daily', 'nginx', 'failed-jobs'],
      errorCount: 9,
      warningCount: 0,
      analysedIssueCount: 0,
      analysisCostUsd: 0.0,
      analysisModel: null,
      discardedEntryCount: 0,
   );
}

it('separates the issue list into one section per channel, queue always last', function (): void {
   $html = (new ErrorAuditMail(multiChannelReport()))->render();

   expect(substr_count($html, '<table class="audit-channel-divider'))->toBe(3)
      ->and($html)->toContain('class="audit-channel-divider audit-channel-divider-queue"')
      ->and($html)->toContain('DAILY')
      ->and($html)->toContain('NGINX')
      ->and($html)->toContain('QUEUE')
      ->and(strpos($html, 'DAILY'))->toBeLessThan(strpos($html, 'NGINX'))
      ->and(strrpos($html, 'QUEUE'))->toBeGreaterThan(strpos($html, 'NGINX'));
});

it('marks a failed job issue with a queue badge', function (): void {
   $html = (new ErrorAuditMail(multiChannelReport()))->render();

   expect($html)->toContain('audit-badge-queue');
});

it('draws no channel divider when every issue shares one channel', function (): void {
   $html = renderAudit();

   expect($html)->not->toContain('<table class="audit-channel-divider');
});

it('states how much of the input token budget the analysis used', function (): void {
   $html = renderAudit();

   expect($html)->toContain('Input used: ~12,840 of 40,000 tokens.');
});

it('words the analysis footnote as separated sentences', function (): void {
   $report = ErrorAuditReportFactory::report();

   expect($report->analysisFootnote())->toBe(
      '5 of 5 issue types analysed by AI. Input used: ~12,840 of 40,000 tokens. Analysis cost: 0.0041 USD (claude-haiku-4-5).'
   );
});

it('shows the token budget even when everything came from cache', function (): void {
   $report = new \Aaix\LaravelErrorAudit\Data\AuditReport(
      applicationName: 'Acme IMS',
      periodStart: \Illuminate\Support\Carbon::parse('2026-07-22 07:00:00'),
      periodEnd: \Illuminate\Support\Carbon::parse('2026-07-23 07:00:00'),
      issues: ErrorAuditReportFactory::report()->issues,
      timeline: [],
      channels: ['daily'],
      errorCount: 5,
      warningCount: 0,
      analysedIssueCount: 5,
      analysisCostUsd: 0.0255,
      analysisModel: 'gemini-3.5-flash-lite',
      discardedEntryCount: 0,
      analysisInputTokens: 0,
      analysisMaxInputTokens: 40000,
   );

   expect($report->analysisFootnote())->toBe(
      '5 of 5 issue types analysed by AI. Input used: ~0 of 40,000 tokens. Analysis cost: 0.0255 USD (gemini-3.5-flash-lite).'
   );
});
