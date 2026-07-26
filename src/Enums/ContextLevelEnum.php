<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Enums;

enum ContextLevelEnum: string
{
   case ClassOnly = 'class_only';
   case MessageOnly = 'message_only';
   case Full = 'full';

   public static function fromMixed(mixed $value): self
   {
      return is_string($value) ? (self::tryFrom(strtolower($value)) ?? self::Full) : self::Full;
   }

   public function includesMessage(): bool
   {
      return $this !== self::ClassOnly;
   }

   public function includesStackFrames(): bool
   {
      return $this === self::Full;
   }
}
