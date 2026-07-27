<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Console;

use Aaix\LaravelErrorAudit\Jobs\GenerateErrorAuditJob;
use Aaix\LaravelErrorAudit\Services\ErrorAuditDispatcher;
use Aaix\LaravelErrorAudit\Services\ErrorAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendErrorAuditCmd extends Command
{
   protected $signature = 'error-audit:send
      {--since= : Start of the analysed period, e.g. "24 hours" or an absolute date}
      {--until= : End of the analysed period, defaults to now}
      {--sync : Build the report in this process instead of dispatching a job}
      {--dry-run : Build the report and print a summary without sending mail}
      {--refresh : Re-analyse issues that already carry a cached assessment}';

   protected $description = 'Generate the AI assisted error audit and mail it to the configured recipients';

   public function handle(ErrorAuditService $service, ErrorAuditDispatcher $dispatcher): int
   {
      [$since, $until] = $this->resolvePeriod($service);

      if (! $this->option('sync') && ! $this->option('dry-run')) {
         GenerateErrorAuditJob::dispatch($since, $until, (bool) $this->option('refresh'));

         $this->components->info('Error audit queued.');

         return self::SUCCESS;
      }

      $progress = new ConsoleProgress($this->output);

      $report = $service->generate($since, $until, (bool) $this->option('refresh'), $progress);

      $this->summarise($report);

      if ($this->option('dry-run')) {
         $this->components->warn('Dry run — no mail was sent.');

         return self::SUCCESS;
      }

      $recipients = implode(', ', $dispatcher->describeRecipients());

      $progress->phase($recipients !== '' ? 'Sending the report to '.$recipients : 'Sending the report');
      $dispatcher->send($report);

      $this->components->info($recipients !== '' ? 'Error audit sent to '.$recipients.'.' : 'Error audit sent.');

      return self::SUCCESS;
   }

   /**
    * @return array{0: Carbon, 1: Carbon}
    */
   private function resolvePeriod(ErrorAuditService $service): array
   {
      $until = $this->option('until') ? Carbon::parse((string) $this->option('until')) : Carbon::now();

      $since = $this->option('since')
         ? $this->parseSince((string) $this->option('since'), $until)
         : $until->copy()->sub($service->period());

      return [$since, $until];
   }

   private function parseSince(string $value, Carbon $until): Carbon
   {
      try {
         $interval = \DateInterval::createFromDateString($value);
      } catch (\Throwable) {
         $interval = false;
      }

      return $interval instanceof \DateInterval
         ? $until->copy()->sub($interval)
         : Carbon::parse($value);
   }

   private function summarise(\Aaix\LaravelErrorAudit\Data\AuditReport $report): void
   {
      $this->newLine();
      $this->components->twoColumnDetail('<fg=gray>Period</>', $report->periodStart->toDateTimeString().' → '.$report->periodEnd->toDateTimeString());
      $this->components->twoColumnDetail('<fg=gray>Status</>', $report->statusWord());
      $this->components->twoColumnDetail('<fg=gray>Errors</>', (string) $report->errorCount);
      $this->components->twoColumnDetail('<fg=gray>Warnings</>', (string) $report->warningCount);
      $this->components->twoColumnDetail('<fg=gray>Issue types</>', $report->issueTypeCount().' ('.$report->newIssueTypeCount().' new)');
      $this->components->twoColumnDetail('<fg=gray>Analysed by AI</>', $report->analysedIssueCount.' of '.$report->issueTypeCount());

      if ($report->analysisMaxInputTokens > 0) {
         $this->components->twoColumnDetail(
            '<fg=gray>Input tokens</>',
            '~'.number_format($report->analysisInputTokens).' of '.number_format($report->analysisMaxInputTokens).' (estimated)',
         );
      }

      $this->components->twoColumnDetail('<fg=gray>Analysis cost</>', number_format($report->analysisCostUsd, 4).' USD');
      $this->newLine();
   }
}
