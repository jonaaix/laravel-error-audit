<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

use Illuminate\Support\Carbon;

final class TimelineBucket
{
   public function __construct(
      public readonly string $key,
      public readonly Carbon $startsAt,
      public readonly string $label,
      public int $errors = 0,
      public int $warnings = 0,
   ) {}

   public function total(): int
   {
      return $this->errors + $this->warnings;
   }
}
