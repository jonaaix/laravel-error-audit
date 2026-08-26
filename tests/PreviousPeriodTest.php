<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Services\ErrorAuditService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
   $this->logPath = sys_get_temp_dir().'/error-audit-previous-'.uniqid().'.log';

   config()->set('logging.default', 'single');
   config()->set('logging.channels.single', ['driver' => 'single', 'path' => $this->logPath]);
});

afterEach(function (): void {
   @unlink($this->logPath);
});

function writeLog(string $path, string $contents): void
{
   file_put_contents($path, $contents);
   touch($path, Carbon::parse('2026-07-23 07:00:00')->getTimestamp());
}

/**
 * The window under audit is 22.07. 07:00 → 23.07. 07:00, so the preceding one
 * is 21.07. 07:00 → 22.07. 07:00.
 */
function auditTwoDays(): Aaix\LaravelErrorAudit\Data\AuditReport
{
   return app(ErrorAuditService::class)->generate(
      since: Carbon::parse('2026-07-22 07:00:00'),
      until: Carbon::parse('2026-07-23 07:00:00'),
   );
}

it('reads the preceding window, so a recurring issue is not reported as new', function (): void {
   writeLog($this->logPath, <<<'LOG'
   [2026-07-21 09:00:00] production.ERROR: Connection refused
   [2026-07-21 10:00:00] production.ERROR: Connection refused
   [2026-07-22 09:00:00] production.ERROR: Connection refused
   LOG);

   $report = auditTwoDays();

   expect($report->issues)->toHaveCount(1)
      ->and($report->issues[0]->isNew)->toBeFalse()
      ->and($report->issues[0]->previousCount)->toBe(2)
      ->and($report->previousErrorCount)->toBe(2);
});

it('still reports an issue absent from the preceding window as new', function (): void {
   writeLog($this->logPath, <<<'LOG'
   [2026-07-21 09:00:00] production.ERROR: Connection refused
   [2026-07-22 09:00:00] production.ERROR: Permission denied
   LOG);

   $report = auditTwoDays();

   expect($report->issues)->toHaveCount(1)
      ->and($report->issues[0]->isNew)->toBeTrue()
      ->and($report->issues[0]->previousCount)->toBeNull();
});

it('carries the preceding totals so the mail can show a trend', function (): void {
   writeLog($this->logPath, <<<'LOG'
   [2026-07-21 09:00:00] production.ERROR: Connection refused
   [2026-07-21 10:00:00] production.WARNING: Disk almost full
   [2026-07-22 09:00:00] production.ERROR: Connection refused
   [2026-07-22 10:00:00] production.ERROR: Connection refused
   LOG);

   $report = auditTwoDays();

   expect($report->errorCount)->toBe(2)
      ->and($report->previousErrorCount)->toBe(1)
      ->and($report->previousWarningCount)->toBe(1)
      ->and($report->errorDeltaPercent())->toBe(100);
});
