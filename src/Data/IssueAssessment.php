<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Data;

use Aaix\LaravelErrorAudit\Enums\IssueCategoryEnum;
use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;

final class IssueAssessment
{
   public function __construct(
      public readonly UrgencyEnum $urgency,
      public readonly IssueCategoryEnum $category,
      public readonly string $title,
      public readonly string $likelyCause,
      public readonly string $suggestedAction,
      public readonly bool $fromCache = false,
   ) {}

   /**
    * @param  array<string, mixed>  $payload
    */
   public static function fromArray(array $payload, bool $fromCache = false): self
   {
      return new self(
         UrgencyEnum::fromMixed($payload['urgency'] ?? null),
         IssueCategoryEnum::fromMixed($payload['category'] ?? null),
         trim((string) ($payload['title'] ?? $payload['summary'] ?? '')),
         trim((string) ($payload['likelyCause'] ?? '')),
         trim((string) ($payload['suggestedAction'] ?? '')),
         $fromCache,
      );
   }
}
