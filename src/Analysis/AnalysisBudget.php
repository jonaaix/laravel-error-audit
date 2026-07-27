<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

class AnalysisBudget
{
   private int $issuesSpent = 0;

   private int $tokensSpent = 0;

   public function __construct(
      private readonly int $maxIssues,
      private readonly int $maxInputTokens,
   ) {}

   public function allows(int $estimatedTokens): bool
   {
      return $this->issuesSpent < $this->maxIssues
         && $this->tokensSpent + $estimatedTokens <= $this->maxInputTokens;
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

   public function maxIssues(): int
   {
      return $this->maxIssues;
   }

   public function maxInputTokens(): int
   {
      return $this->maxInputTokens;
   }
}
