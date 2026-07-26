<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Http;

use Aaix\LaravelErrorAudit\Agents\ErrorAuditAgent;
use Aaix\LaravelErrorAudit\Analysis\IssuePayloadBuilder;
use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\Mail\ErrorAuditMail;
use Aaix\LaravelErrorAudit\Services\ErrorAuditService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PreviewErrorAuditController
{
   private const CACHE_PREFIX = 'error-audit:preview:';

   public function __construct(private readonly CacheRepository $cache) {}

   /**
    * Render the report in the browser exactly as it would be mailed.
    *
    * The generated report is cached, because reading the logs and rendering the
    * chart is too slow to sit between a template edit and seeing the result.
    * Append ?fresh=1 to rebuild it.
    *
    * The chart arrives as a data URI here rather than an embedded attachment,
    * since there is no message to attach it to. Everything else is identical.
    */
   public function __invoke(Request $request, ErrorAuditService $service): string
   {
      $until = $this->parseMoment($request->query('until')) ?? Carbon::now()->startOfHour();

      $interval = $this->parseInterval($request->query('since'));
      $since = $interval !== null
         ? $until->copy()->sub($interval)
         : $until->copy()->sub($service->period());

      if ($request->boolean('prompts')) {
         return $this->renderPrompts($service, $since, $until);
      }

      $key = self::CACHE_PREFIX.md5($since->toIso8601String().$until->toIso8601String());

      if ($request->boolean('fresh') || $request->boolean('refresh')) {
         $this->cache->forget($key);
      }

      $report = $this->cache->remember(
         $key,
         now()->addHours(6),
         fn (): AuditReport => $service->generate($since, $until, $request->boolean('refresh')),
      );

      return (new ErrorAuditMail($report))->render();
   }

   /**
    * Show exactly what would be sent to the AI provider: the shared system
    * instructions plus one payload per issue, source files and all. No request
    * is made — this only assembles the prompts.
    */
   private function renderPrompts(ErrorAuditService $service, Carbon $since, Carbon $until): string
   {
      $builder = app(IssuePayloadBuilder::class);
      $collected = $service->collect($since, $until);
      $period = $service->describePeriod($since, $until);
      $limit = (int) app(\Aaix\LaravelErrorAudit\ErrorAudit::class)->value('ai.max_issues_per_run', 15);

      $prompts = [];

      foreach (array_slice($collected->groupsByFrequency(), 0, $limit) as $group) {
         $prompts[] = [
            'title' => $group->title(),
            'level' => $group->level->label(),
            'count' => $group->count(),
            'payload' => $builder->build($group, $period),
         ];
      }

      return view('error-audit::preview.prompts', [
         'instructions' => (string) (new ErrorAuditAgent)->instructions(),
         'prompts' => $prompts,
         'period' => $period,
      ])->render();
   }

   private function parseMoment(mixed $value): ?Carbon
   {
      if (! is_string($value) || $value === '') {
         return null;
      }

      try {
         return Carbon::parse($value);
      } catch (\Throwable) {
         return null;
      }
   }

   private function parseInterval(mixed $value): ?\DateInterval
   {
      if (! is_string($value) || $value === '') {
         return null;
      }

      try {
         return \DateInterval::createFromDateString($value) ?: null;
      } catch (\Throwable) {
         return null;
      }
   }
}
