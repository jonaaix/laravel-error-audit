<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Enums;

enum AnalysisOutcomeEnum: string
{
   case Analysed = 'analysed';
   case Cached = 'cached';
   case SkippedBudget = 'skipped-budget';
   case Failed = 'failed';
}
