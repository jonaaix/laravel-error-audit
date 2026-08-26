<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Enums;

enum AnalysisOutcomeEnum: string
{
   case Analysed = 'analysed';
   case Cached = 'cached';
   case Disabled = 'disabled';
   case SkippedBudget = 'skipped-budget';
   case SkippedCost = 'skipped-cost';
   case Failed = 'failed';

   /**
    * What the report tells the reader when no assessment came out of the run.
    * Analysed and cached issues carry one, so they have nothing to explain.
    */
   public function explanation(): ?string
   {
      return match ($this) {
         self::Analysed, self::Cached => null,
         self::Disabled => __('Not analysed — AI analysis is switched off. Counted and tracked all the same.'),
         self::SkippedBudget => __('Not analysed — beyond the analysis budget for this report. Counted and tracked; it will be analysed on the next run if it persists.'),
         self::SkippedCost => __('Not analysed — the daily analysis cost limit was already reached. Counted and tracked; it will be analysed on the next run if it persists.'),
         self::Failed => __('Not analysed — the request to the AI provider failed. Counted and tracked; the application log holds the reason.'),
      };
   }
}
