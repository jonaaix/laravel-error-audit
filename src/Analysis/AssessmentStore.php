<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Data\IssueAssessment;
use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Remembers what has already been assessed, so a recurring issue is never sent
 * to the provider twice and the report can tell a new failure from a known one.
 *
 * The framework cache is enough for this: losing it costs nothing but a single
 * re-analysis, which is why the package ships no schema of its own.
 */
class AssessmentStore
{
   private const PREFIX = 'error-audit:issue:';

   public function __construct(private readonly CacheRepository $cache) {}

   public function remember(IssueGroup $group, ?IssueAssessment $assessment, ?string $model, ?float $costUsd): void
   {
      $known = $this->find($group->fingerprint);

      $entry = [
         'first_seen_at' => $known['first_seen_at'] ?? $group->firstSeen()->toIso8601String(),
         'last_seen_at' => $group->lastSeen()->toIso8601String(),
         'last_count' => $group->count(),
         'total_count' => ($known['total_count'] ?? 0) + $group->count(),
         'level' => $group->level->value,
      ];

      if ($assessment !== null && ! $assessment->fromCache) {
         $entry += [
            'urgency' => $assessment->urgency->value,
            'category' => $assessment->category->value,
            'title' => $assessment->title,
            'likelyCause' => $assessment->likelyCause,
            'suggestedAction' => $assessment->suggestedAction,
            'analysed_at' => Carbon::now()->toIso8601String(),
            'model' => $model,
            'cost_usd' => $costUsd,
         ];
      } elseif ($known !== null) {
         $entry += array_intersect_key($known, array_flip([
            'urgency', 'category', 'title', 'likelyCause', 'suggestedAction',
            'analysed_at', 'model', 'cost_usd',
         ]));
      }

      $this->cache->forever(self::PREFIX.$group->fingerprint, $entry);
      $this->cache->forever(self::PREFIX.'index', $this->indexWith($group->fingerprint));
   }

   /**
    * @return array<string, mixed>|null
    */
   public function find(string $fingerprint): ?array
   {
      $entry = $this->cache->get(self::PREFIX.$fingerprint);

      return is_array($entry) ? $entry : null;
   }

   public function assessmentFor(string $fingerprint): ?IssueAssessment
   {
      $entry = $this->find($fingerprint);

      if ($entry === null || ! isset($entry['urgency'], $entry['title'])) {
         return null;
      }

      return IssueAssessment::fromArray($entry, fromCache: true);
   }

   public function firstSeen(string $fingerprint): ?Carbon
   {
      $entry = $this->find($fingerprint);

      return isset($entry['first_seen_at']) ? Carbon::parse($entry['first_seen_at']) : null;
   }

   /**
    * @return list<string>
    */
   public function index(): array
   {
      $index = $this->cache->get(self::PREFIX.'index');

      return is_array($index) ? array_values($index) : [];
   }

   /**
    * @return list<string>
    */
   private function indexWith(string $fingerprint): array
   {
      $index = $this->index();

      if (! in_array($fingerprint, $index, true)) {
         $index[] = $fingerprint;
      }

      return $index;
   }
}
