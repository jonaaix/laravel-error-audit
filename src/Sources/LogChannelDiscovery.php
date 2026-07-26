<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Sources;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;

class LogChannelDiscovery
{
   private const FILE_HANDLERS = [
      StreamHandler::class,
      RotatingFileHandler::class,
   ];

   public function __construct(private readonly ConfigRepository $config) {}

   /**
    * Resolve every file based log channel into the concrete files it writes to.
    *
    * Non file based drivers (slack, syslog, papertrail, errorlog, null) carry no
    * readable history and are skipped. Stacks are expanded recursively.
    *
    * @return list<DiscoveredLogFile>
    */
   public function discover(): array
   {
      $definitions = (array) $this->config->get('logging.channels', []);

      $files = [];
      $seenPaths = [];

      foreach ($this->channelOrder($definitions) as $channel) {
         $this->resolveChannel($channel, $definitions, $files, $seenPaths, []);
      }

      return array_values($files);
   }

   /**
    * The application's default channel is resolved first so its files lead the report.
    *
    * @param  array<string, mixed>  $definitions
    * @return list<string>
    */
   private function channelOrder(array $definitions): array
   {
      $names = array_keys($definitions);
      $default = (string) $this->config->get('logging.default');

      if ($default !== '' && in_array($default, $names, true)) {
         $names = array_merge([$default], array_values(array_diff($names, [$default])));
      }

      return array_values(array_filter($names, fn (string $name): bool => $this->isSelected($name)));
   }

   private function isSelected(string $channel): bool
   {
      $include = (array) $this->config->get('error-audit.channels.include', ['*']);
      $exclude = (array) $this->config->get('error-audit.channels.exclude', []);

      foreach ($exclude as $pattern) {
         if (Str::is($pattern, $channel)) {
            return false;
         }
      }

      foreach ($include as $pattern) {
         if (Str::is($pattern, $channel)) {
            return true;
         }
      }

      return false;
   }

   /**
    * @param  array<string, mixed>  $definitions
    * @param  array<string, DiscoveredLogFile>  $files
    * @param  array<string, true>  $seenPaths
    * @param  list<string>  $visited
    */
   private function resolveChannel(
      string $channel,
      array $definitions,
      array &$files,
      array &$seenPaths,
      array $visited,
   ): void {
      if (in_array($channel, $visited, true)) {
         return;
      }

      $visited[] = $channel;
      $definition = $definitions[$channel] ?? null;

      if (! is_array($definition)) {
         return;
      }

      $driver = $definition['driver'] ?? null;

      if ($driver === 'stack') {
         foreach ((array) ($definition['channels'] ?? []) as $member) {
            $this->resolveChannel((string) $member, $definitions, $files, $seenPaths, $visited);
         }

         return;
      }

      foreach ($this->pathsFor($driver, $definition) as $path) {
         if (isset($seenPaths[$path])) {
            continue;
         }

         $seenPaths[$path] = true;
         $files[$path] = new DiscoveredLogFile($path, $channel);
      }
   }

   /**
    * @param  array<string, mixed>  $definition
    * @return list<string>
    */
   private function pathsFor(mixed $driver, array $definition): array
   {
      return match ($driver) {
         'single' => $this->existing([(string) ($definition['path'] ?? '')]),
         'daily' => $this->rotatedPaths((string) ($definition['path'] ?? '')),
         'monolog' => $this->monologPaths($definition),
         default => [],
      };
   }

   /**
    * A daily channel writes "laravel-2026-07-23.log" beside the configured path.
    *
    * @return list<string>
    */
   private function rotatedPaths(string $path): array
   {
      if ($path === '') {
         return [];
      }

      $directory = dirname($path);
      $extension = pathinfo($path, PATHINFO_EXTENSION);
      $stem = pathinfo($path, PATHINFO_FILENAME);

      $pattern = $extension === ''
         ? $directory.DIRECTORY_SEPARATOR.$stem.'-*'
         : $directory.DIRECTORY_SEPARATOR.$stem.'-*.'.$extension;

      $matches = glob($pattern) ?: [];

      return $this->existing(array_merge([$path], $matches));
   }

   /**
    * @param  array<string, mixed>  $definition
    * @return list<string>
    */
   private function monologPaths(array $definition): array
   {
      $handler = $definition['handler'] ?? null;

      if (! is_string($handler) || ! in_array($handler, self::FILE_HANDLERS, true)) {
         return [];
      }

      $with = (array) ($definition['with'] ?? []);
      $target = $with['stream'] ?? $with['filename'] ?? null;

      if (! is_string($target)) {
         return [];
      }

      return $handler === RotatingFileHandler::class
         ? $this->rotatedPaths($target)
         : $this->existing([$target]);
   }

   /**
    * @param  list<string>  $paths
    * @return list<string>
    */
   private function existing(array $paths): array
   {
      $resolved = [];

      foreach ($paths as $path) {
         if ($path !== '' && is_file($path) && is_readable($path)) {
            $resolved[] = $path;
         }
      }

      return array_values(array_unique($resolved));
   }
}
