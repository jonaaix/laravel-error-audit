<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

/**
 * Per-run caps on the analysis. Every limit is optional: null switches it
 * off, so an application can steer purely by the daily cost limit instead.
 */
class AnalysisBudget
{
   private int $issuesSpent = 0;

   private int $tokensSpent = 0;

   public function __construct(
      private readonly ?int $maxIssues,
      private readonly ?int $maxInputTokens,
   ) {}

   public function allows(int $estimatedTokens): bool
   {
      return ($this->maxIssues === null || $this->issuesSpent < $this->maxIssues)
         && ($this->maxInputTokens === null || $this->tokensSpent + $estimatedTokens <= $this->maxInputTokens);
   }

   public function consume(int $estimatedTokens): void
   {
      $this->issuesSpent++;
      $this->tokensSpent += $estimatedTokens;
   }

   public function issuesSpent(): int
   {
      return $this->issuesSpent;
   }

   public function tokensSpent(): int
   {
      return $this->tokensSpent;
   }

   public function maxIssues(): ?int
   {
      return $this->maxIssues;
   }

   public function maxInputTokens(): ?int
   {
      return $this->maxInputTokens;
   }
}
