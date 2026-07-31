<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

/**
 * Sums what the analysis actually spent per calendar day, in the framework
 * cache. This is what a money limit needs that a token limit cannot give:
 * real billed cost, accumulated across every run of the day — scheduled,
 * manual and --refresh alike.
 */
class DailyCostLedger
{
   private const PREFIX = 'error-audit:spend:';

   public function __construct(private readonly CacheRepository $cache) {}

   public function add(float $costUsd): void
   {
      if ($costUsd <= 0) {
         return;
      }

      $this->cache->put($this->key(), $this->spentToday() + $costUsd, Carbon::now()->addDays(2));
   }

   public function spentToday(): float
   {
      return (float) $this->cache->get($this->key(), 0.0);
   }

   private function key(): string
   {
      return self::PREFIX.Carbon::now()->format('Y-m-d');
   }
}
