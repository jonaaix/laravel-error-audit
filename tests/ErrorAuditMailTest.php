<?php

declare(strict_types=1);

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

it('says what the change rate is measured against', function (): void {
   $html = renderAudit();

   expect($html)->toContain('▲ +38%')
      ->and($html)->toContain('Change compared to the preceding 1 day.');
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

it('marks new issue types', function (): void {
   expect(renderAudit())->toContain('NEW');
});

it('reports the analysis cost and coverage', function (): void {
   expect(renderAudit())->toContain('5 of 5 issue types analysed by AI')
      ->and(renderAudit())->toContain('0.0041');
});

it('inlines the packaged theme instead of the framework default', function (): void {
   expect(renderAudit())->toContain('audit-summary');
});

it('carries the status signal in the subject line', function (): void {
   $envelope = (new ErrorAuditMail(ErrorAuditReportFactory::report()))->envelope();

   expect($envelope->subject)->toBe('⚠ 2 new issue types · 526 errors — Acme IMS, 23.07.');
});

it('states plainly when an issue was not analysed', function (): void {
   expect(renderAudit(withAssessments: false))->toContain('beyond the analysis budget');
});

it('ships a plain text alternative without markup', function (): void {
   $report = ErrorAuditReportFactory::report();
   $content = (new ErrorAuditMail($report))->content();

   expect($content->text)->toBe('error-audit::mail.report-text');

   $text = view($content->text, ['report' => $report])->render();

   expect($text)->toContain('Acme IMS')
      ->and($text)->toContain('QueryException')
      ->and($text)->not->toContain('<table');
});


