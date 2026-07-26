<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Console;

use Aaix\LaravelErrorAudit\Analysis\IssuePayloadBuilder;
use Aaix\LaravelErrorAudit\Services\ErrorAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PreviewErrorAuditCmd extends Command
{
   protected $signature = 'error-audit:preview
      {--since= : Start of the analysed period, e.g. "24 hours"}
      {--limit=5 : How many issue payloads to print}';

   protected $description = 'Print the redacted payloads that would be sent to the AI provider, without calling it';

   public function handle(ErrorAuditService $service, IssuePayloadBuilder $builder): int
   {
      $until = Carbon::now();
      $since = $this->option('since')
         ? $until->copy()->sub(\DateInterval::createFromDateString((string) $this->option('since')))
         : $until->copy()->sub($service->period());

      $collected = $service->collect($since, $until);
      $groups = $collected->groupsByFrequency();

      if ($groups === []) {
         $this->components->info('No errors or warnings found in the selected period.');

         return self::SUCCESS;
      }

      $this->components->warn('The following text is everything the AI provider would receive.');
      $this->newLine();

      foreach (array_slice($groups, 0, (int) $this->option('limit')) as $group) {
         $this->line('<fg=yellow>'.str_repeat('─', 70).'</>');
         $this->line($builder->build($group, $service->describePeriod($since, $until)));
         $this->newLine();
      }

      $remaining = count($groups) - (int) $this->option('limit');

      if ($remaining > 0) {
         $this->components->info($remaining.' further issue types not shown. Raise --limit to inspect them.');
      }

      return self::SUCCESS;
   }
}
