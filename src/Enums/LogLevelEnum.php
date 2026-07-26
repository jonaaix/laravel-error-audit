<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Enums;

enum LogLevelEnum: string
{
   case Debug = 'debug';
   case Info = 'info';
   case Notice = 'notice';
   case Warning = 'warning';
   case Error = 'error';
   case Critical = 'critical';
   case Alert = 'alert';
   case Emergency = 'emergency';

   public static function tryFromLabel(string $label): ?self
   {
      return self::tryFrom(strtolower(trim($label)));
   }

   public function severity(): int
   {
      return match ($this) {
         self::Debug => 0,
         self::Info => 1,
         self::Notice => 2,
         self::Warning => 3,
         self::Error => 4,
         self::Critical => 5,
         self::Alert => 6,
         self::Emergency => 7,
      };
   }

   public function isAtLeast(self $minimum): bool
   {
      return $this->severity() >= $minimum->severity();
   }

   public function isError(): bool
   {
      return $this->severity() >= self::Error->severity();
   }

   public function label(): string
   {
      return strtoupper($this->value);
   }

   /**
    * The colours follow the severity Laravel itself assigns, not anyone's
    * opinion of it — emergency through error are red, warning is amber.
    */
   public function textColor(): string
   {
      return match (true) {
         $this->severity() >= self::Critical->severity() => '#991b1b',
         $this->severity() >= self::Error->severity() => '#DC2626',
         $this === self::Warning => '#B45309',
         default => '#6B7280',
      };
   }

   public function tintColor(): string
   {
      return match (true) {
         $this->severity() >= self::Critical->severity() => '#fbd5d5',
         $this->severity() >= self::Error->severity() => '#fdeaea',
         $this === self::Warning => '#fef6e0',
         default => '#f4f4f5',
      };
   }
}
