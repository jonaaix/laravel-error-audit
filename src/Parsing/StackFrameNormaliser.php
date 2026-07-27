<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Parsing;

/**
 * Reduces a raw stack trace line to file, line and the invoked method, in the
 * same shape regardless of where the trace came from — a log file or the
 * queue's failed-job store. Argument values never survive normalisation, so
 * they cannot leak into an analysis payload.
 */
class StackFrameNormaliser
{
   private const FRAME = '/^#\d+\s+(?<body>.*)$/';

   public function normalise(string $line): ?string
   {
      $line = trim($line);

      if (preg_match(self::FRAME, $line, $matches) !== 1) {
         return null;
      }

      $body = $matches['body'];

      if ($body === '{main}') {
         return null;
      }

      $location = null;
      $call = null;

      if (preg_match('/^(?<file>.+?)\((?<line>\d+)\)\s*:\s*(?<call>.*)$/', $body, $parts) === 1) {
         $location = $this->relativePath($parts['file']).':'.$parts['line'];
         $call = $parts['call'];
      } elseif (preg_match('/^\[internal function\]\s*:\s*(?<call>.*)$/', $body, $parts) === 1) {
         $location = '[internal]';
         $call = $parts['call'];
      } else {
         return $this->relativePath($body);
      }

      $call = preg_replace('/\(.*$/s', '()', trim($call)) ?? '';

      return trim($location.' '.$this->unescape($call));
   }

   /**
    * The frame worth showing is the first one inside the application, which is
    * often buried well below the vendor frames that raised the exception.
    */
   public function isApplicationFrame(string $frame): bool
   {
      if (str_contains($frame, 'vendor/') || str_starts_with($frame, '[internal]')) {
         return false;
      }

      return (bool) preg_match('#^(app|packages|routes|config|database|bootstrap)/#', $frame);
   }

   private function relativePath(string $path): string
   {
      $base = function_exists('base_path') ? base_path() : '';

      return $base !== '' && str_starts_with($path, $base)
         ? ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR)
         : $path;
   }

   private function unescape(string $value): string
   {
      return ltrim(str_replace('\\\\', '\\', $value), '\\');
   }
}
