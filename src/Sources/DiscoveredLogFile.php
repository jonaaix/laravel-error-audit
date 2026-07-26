<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Sources;

final class DiscoveredLogFile
{
   public function __construct(
      public readonly string $path,
      public readonly string $channel,
   ) {}
}
