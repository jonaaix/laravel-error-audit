<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Contracts;

use Aaix\LaravelErrorAudit\Data\ChartSeries;
use Aaix\LaravelErrorAudit\Data\TimelineBucket;

interface ChartRenderer
{
   /**
    * Render the timeline as a PNG, or return null when this environment cannot
    * produce one. A missing chart degrades the report; it never breaks it.
    *
    * @param  list<TimelineBucket>  $timeline
    * @param  list<ChartSeries>  $series
    */
   public function render(array $timeline, array $series): ?string;
}
