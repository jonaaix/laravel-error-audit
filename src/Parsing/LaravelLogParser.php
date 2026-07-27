<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Parsing;

use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Sources\DiscoveredLogFile;
use Generator;
use Illuminate\Support\Carbon;
use Throwable;

class LaravelLogParser
{
   private const HEADER = '/^\[(?<time>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:\s?[+-]\d{2}:?\d{2}|Z)?)\]\s(?<env>[^\s\[\]]+?)\.(?<level>[A-Z]+):\s?(?<message>.*)$/';

   private const EXCEPTION_PREFIX = '/^(?<class>\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+|[A-Za-z_][A-Za-z0-9_]*(?:Exception|Error))\s*:\s/';

   /**
    * Laravel puts the real exception class inside the serialised context rather
    * than in front of the message, so the message prefix alone misses most of them.
    */
   private const EXCEPTION_CONTEXT = '/\[object\]\s*\((?<class>[A-Za-z_][A-Za-z0-9_\\\\]*)\s*\(code/';

   private const CONTEXT_BLOB = '/\s*\{"(?:exception|userId|email)".*$/s';

   private readonly StackFrameNormaliser $frames;

   public function __construct(
      private readonly ?int $maxStackFrames = null,
      ?StackFrameNormaliser $frames = null,
   ) {
      $this->frames = $frames ?? new StackFrameNormaliser;
   }

   /**
    * Stream a log file entry by entry. Lines that do not open a new entry belong
    * to the stack trace of the entry that preceded them.
    *
    * @return Generator<int, LogEntry>
    */
   public function parse(
      DiscoveredLogFile $file,
      Carbon $since,
      Carbon $until,
      LogLevelEnum $minimumLevel,
   ): Generator {
      $handle = @fopen($file->path, 'rb');

      if ($handle === false) {
         return;
      }

      $header = null;
      $frames = [];
      $appFrame = null;

      try {
         while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if (preg_match(self::HEADER, $line, $matches) === 1) {
               if ($header !== null) {
                  $entry = $this->build($header, $frames, $appFrame, $file, $since, $until, $minimumLevel);

                  if ($entry !== null) {
                     yield $entry;
                  }
               }

               $header = $matches;
               $frames = [];
               $appFrame = null;

               continue;
            }

            if ($header === null) {
               continue;
            }

            $frame = $this->frames->normalise($line);

            if ($frame === null) {
               continue;
            }

            if ($this->maxStackFrames === null || count($frames) < $this->maxStackFrames) {
               $frames[] = $frame;
            }

            if ($appFrame === null && $this->frames->isApplicationFrame($frame)) {
               $appFrame = $frame;
            }
         }

         if ($header !== null) {
            $entry = $this->build($header, $frames, $appFrame, $file, $since, $until, $minimumLevel);

            if ($entry !== null) {
               yield $entry;
            }
         }
      } finally {
         fclose($handle);
      }
   }

   /**
    * @param  array<string, string>  $header
    * @param  list<string>  $frames
    */
   private function build(
      array $header,
      array $frames,
      ?string $appFrame,
      DiscoveredLogFile $file,
      Carbon $since,
      Carbon $until,
      LogLevelEnum $minimumLevel,
   ): ?LogEntry {
      $level = LogLevelEnum::tryFromLabel($header['level']);

      if ($level === null || ! $level->isAtLeast($minimumLevel)) {
         return null;
      }

      try {
         $loggedAt = Carbon::parse($header['time']);
      } catch (Throwable) {
         return null;
      }

      if ($loggedAt->lessThan($since) || $loggedAt->greaterThan($until)) {
         return null;
      }

      $raw = trim($header['message']);

      return new LogEntry(
         loggedAt: $loggedAt,
         level: $level,
         channel: $file->channel,
         environment: $header['env'],
         message: $this->withoutContextBlob($raw),
         exceptionClass: $this->exceptionClass($raw),
         stackFrames: $frames,
         appFrame: $appFrame,
      );
   }

   /**
    * The serialised context repeats the message and the trace verbatim. Both are
    * already captured, so carrying it further only inflates the analysis payload.
    */
   private function withoutContextBlob(string $message): string
   {
      return rtrim((string) preg_replace(self::CONTEXT_BLOB, '', $message));
   }

   private function exceptionClass(string $message): ?string
   {
      if (preg_match(self::EXCEPTION_CONTEXT, $message, $matches) === 1) {
         return $this->unescape($matches['class']);
      }

      if (preg_match(self::EXCEPTION_PREFIX, $message, $matches) !== 1) {
         return null;
      }

      $class = $this->unescape($matches['class']);

      $looksLikeClass = str_contains($class, '\\')
         || str_ends_with($class, 'Exception')
         || str_ends_with($class, 'Error');

      return $looksLikeClass ? $class : null;
   }

   private function unescape(string $value): string
   {
      return ltrim(str_replace('\\\\', '\\', $value), '\\');
   }

}
