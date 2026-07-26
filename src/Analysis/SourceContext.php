<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Redaction\LogRedactor;

/**
 * Loads the application's own source files that appear in a stack trace, so the
 * model can read what the code at the failure site actually does rather than
 * inferring it from a file:line reference.
 *
 * Strictly whitelisted: only the application's own directories are ever read,
 * never vendor and never a path that resolves outside the project root. The
 * files still pass through redaction, so a hardcoded secret does not leak.
 */
class SourceContext
{
   private const APP_DIRECTORIES = 'app|packages|routes|config|database|bootstrap|tests';

   public function __construct(
      private readonly LogRedactor $redactor,
      private readonly string $basePath,
      private readonly int $maxFileBytes = 200000,
   ) {}

   /**
    * @param  list<string>  $frames
    * @return array<string, string>  Relative path => redacted source.
    */
   public function forFrames(array $frames): array
   {
      $paths = [];

      foreach ($frames as $frame) {
         if (preg_match('#^((?:'.self::APP_DIRECTORIES.')/[^\s:]+\.php)#', $frame, $matches) === 1) {
            $paths[$matches[1]] = true;
         }
      }

      $sources = [];

      foreach (array_keys($paths) as $relative) {
         $content = $this->read($relative);

         if ($content !== null) {
            $sources[$relative] = $content;
         }
      }

      return $sources;
   }

   private function read(string $relative): ?string
   {
      // Canonicalise the base as well: base_path() may sit behind a symlink
      // (macOS temp dirs, "current" symlinks in atomic deployments), and the
      // prefix check below compares resolved against unresolved paths otherwise.
      $base = realpath(rtrim($this->basePath, DIRECTORY_SEPARATOR));

      if ($base === false) {
         return null;
      }

      $real = realpath($base.DIRECTORY_SEPARATOR.$relative);

      if ($real === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
         return null;
      }

      if (! is_file($real) || ! is_readable($real)) {
         return null;
      }

      $size = filesize($real);

      if ($size === false || $size > $this->maxFileBytes) {
         return null;
      }

      $content = file_get_contents($real);

      if ($content === false) {
         return null;
      }

      return $this->redactor->redact($content)->text;
   }
}
