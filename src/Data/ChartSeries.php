<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

final class ChartSeries
{
   /**
    * @param  list<int>  $data  One value per timeline bucket.
    * @param  'errors'|'warnings'  $stack
    */
   public function __construct(
      public readonly string $label,
      public readonly string $stack,
      public readonly string $color,
      public readonly array $data,
   ) {}
}
