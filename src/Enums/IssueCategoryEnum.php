<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Enums;

enum IssueCategoryEnum: string
{
   case Bug = 'bug';
   case Configuration = 'configuration';
   case Integration = 'integration';
   case Performance = 'performance';
   case Security = 'security';
   case Deprecation = 'deprecation';
   case Infrastructure = 'infrastructure';
   case Noise = 'noise';

   public static function fromMixed(mixed $value): self
   {
      return is_string($value) ? (self::tryFrom(strtolower($value)) ?? self::Bug) : self::Bug;
   }

   public function label(): string
   {
      return match ($this) {
         self::Bug => __('Bug'),
         self::Configuration => __('Configuration'),
         self::Integration => __('Integration'),
         self::Performance => __('Performance'),
         self::Security => __('Security'),
         self::Deprecation => __('Deprecation'),
         self::Infrastructure => __('Infrastructure'),
         self::Noise => __('Noise'),
      };
   }
}
