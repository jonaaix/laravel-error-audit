<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Enums;

enum UrgencyEnum: string
{
   case Critical = 'critical';
   case High = 'high';
   case Medium = 'medium';
   case Low = 'low';
   case Noise = 'noise';

   public static function fromMixed(mixed $value): self
   {
      return is_string($value) ? (self::tryFrom(strtolower($value)) ?? self::Medium) : self::Medium;
   }

   public function label(): string
   {
      return match ($this) {
         self::Critical => __('Critical'),
         self::High => __('High'),
         self::Medium => __('Medium'),
         self::Low => __('Low'),
         self::Noise => __('Noise'),
      };
   }

   public function rank(): int
   {
      return match ($this) {
         self::Critical => 0,
         self::High => 1,
         self::Medium => 2,
         self::Low => 3,
         self::Noise => 4,
      };
   }

   public function textColor(): string
   {
      return match ($this) {
         self::Critical, self::High => '#DC2626',
         self::Medium => '#B45309',
         self::Low, self::Noise => '#6B7280',
      };
   }

   public function tintColor(): string
   {
      return match ($this) {
         self::Critical, self::High => '#fdeaea',
         self::Medium => '#fef6e0',
         self::Low, self::Noise => '#f4f4f5',
      };
   }

   /**
    * A violet intensity ramp, deliberately its own colour family: red and amber
    * already carry the log level, so the AI's own judgement gets a hue that
    * collides with nothing — and matches the documentation's accent.
    *
    * @return array{background: string, text: string, border: string}
    */
   public function badgeStyle(): array
   {
      return match ($this) {
         self::Critical => ['background' => '#6D28D9', 'text' => '#ffffff', 'border' => '#6D28D9'],
         self::High => ['background' => '#ede9fe', 'text' => '#6D28D9', 'border' => '#ddd6fe'],
         self::Medium => ['background' => '#f5f3ff', 'text' => '#7C3AED', 'border' => '#ede9fe'],
         self::Low => ['background' => '#ffffff', 'text' => '#8B5CF6', 'border' => '#ddd6fe'],
         self::Noise => ['background' => '#ffffff', 'text' => '#a1a1aa', 'border' => '#e4e4e7'],
      };
   }
}
