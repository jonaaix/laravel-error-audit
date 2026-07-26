<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Illuminate\Support\Carbon;

final class LogEntry
{
   /**
    * @param  list<string>  $stackFrames
    */
   public function __construct(
      public readonly Carbon $loggedAt,
      public readonly LogLevelEnum $level,
      public readonly string $channel,
      public readonly string $environment,
      public readonly string $message,
      public readonly ?string $exceptionClass = null,
      public readonly array $stackFrames = [],
      public readonly ?string $appFrame = null,
   ) {}
}
