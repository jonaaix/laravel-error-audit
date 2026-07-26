<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Redaction;

final class RedactionResult
{
   public function __construct(
      public readonly string $text,
      public readonly float $maskedRatio,
      public readonly bool $shouldDiscard,
   ) {}
}
