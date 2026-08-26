<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Console;

use Aaix\LaravelErrorAudit\Contracts\AuditProgress;
use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;
use Illuminate\Console\OutputStyle;

/**
 * Renders the run live on the console: one bold line per phase, gray detail
 * lines beneath it, and one line per analysed issue with its outcome.
 */
class ConsoleProgress implements AuditProgress
{
   public function __construct(private readonly OutputStyle $output) {}

   public function phase(string $description): void
   {
      $this->output->newLine();
      $this->output->writeln('  <options=bold>'.$description.'</>');
   }

   public function detail(string $description): void
   {
      $this->output->writeln('  <fg=gray>'.$description.'</>');
   }

   public function issue(string $title, int $occurrences, AnalysisOutcomeEnum $outcome, ?float $costUsd = null): void
   {
      [$mark, $label] = match ($outcome) {
         AnalysisOutcomeEnum::Analysed => [
            '<fg=green>✓</>',
            'analysed'.($costUsd !== null && $costUsd > 0 ? sprintf(' · $%.4f', $costUsd) : ''),
         ],
         AnalysisOutcomeEnum::Cached => ['<fg=cyan>✓</>', 'cached — no request'],
         AnalysisOutcomeEnum::Disabled => ['<fg=gray>○</>', 'not assessed — AI analysis is switched off'],
         AnalysisOutcomeEnum::SkippedBudget => ['<fg=yellow>○</>', 'skipped — analysis budget exhausted'],
         AnalysisOutcomeEnum::SkippedCost => ['<fg=yellow>○</>', 'skipped — daily cost limit reached'],
         AnalysisOutcomeEnum::Failed => ['<fg=red>✗</>', 'analysis failed — see the application log'],
      };

      $this->output->writeln(sprintf(
         '  %s %s <fg=gray>×%s — %s</>',
         $mark,
         $title,
         number_format($occurrences),
         $label,
      ));
   }
}
