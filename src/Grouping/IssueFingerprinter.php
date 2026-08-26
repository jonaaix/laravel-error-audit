<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Grouping;

use Aaix\LaravelErrorAudit\Data\LogEntry;

class IssueFingerprinter
{
   private const SIGNATURE_LENGTH = 240;

   /**
    * Ordered so that broad patterns never consume the narrow ones before them.
    *
    * The timestamp tail is spelled out rather than matched as non-whitespace:
    * a serialised payload puts no space after a timestamp, so anything greedier
    * eats the rest of the payload, unbalances its quotes, and leaves volatile
    * values in the signature — one failure then becomes one group per payload.
    *
    * @var array<string, string>
    */
   private const REPLACEMENTS = [
      '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i' => '{uuid}',
      '/\b\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?(?:Z|[+-]\d{2}:?\d{2})?/' => '{timestamp}',
      '/\b\d{4}-\d{2}-\d{2}\b/' => '{date}',
      '/\b[\w.+-]+@[\w-]+\.[\w.-]+\b/' => '{email}',
      '/\bhttps?:\/\/\S+/i' => '{url}',
      '/(?:\/[\w.@-]+){2,}(?::\d+)?/' => '{path}',
      '/\b[0-9a-f]{16,}\b/i' => '{hash}',
      '/"[^"]*"/' => '"{value}"',
      "/'[^']*'/" => "'{value}'",
      '/\b\d[\d.,]*\b/' => '{n}',
      '/\s+/' => ' ',
   ];

   public function fingerprint(LogEntry $entry): string
   {
      return sha1(implode('|', [
         $entry->exceptionClass ?? '',
         $entry->level->value,
         $this->signature($entry->message),
         $this->firstFrame($entry),
      ]));
   }

   public function signature(string $message): string
   {
      $normalised = $message;

      foreach (self::REPLACEMENTS as $pattern => $replacement) {
         $normalised = preg_replace($pattern, $replacement, $normalised) ?? $normalised;
      }

      return mb_substr(trim($normalised), 0, self::SIGNATURE_LENGTH);
   }

   private function firstFrame(LogEntry $entry): string
   {
      foreach ($entry->stackFrames as $frame) {
         if (! str_contains($frame, 'vendor/')) {
            return $frame;
         }
      }

      return $entry->stackFrames[0] ?? '';
   }
}
