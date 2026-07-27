<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Sources;

use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\ErrorAudit;
use Aaix\LaravelErrorAudit\Parsing\StackFrameNormaliser;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A job that exhausts its retries raises an exception that never reaches a log
 * file — it lands in the queue's failed-job store instead. This source reads
 * that store for the audited period so failed jobs appear in the report like
 * any other issue.
 *
 * Strictly best effort: not every application has a failed-job table, and only
 * the database backed drivers can be queried by time window. Anything else —
 * a missing table, a different driver, a connection that cannot be reached —
 * silently contributes nothing rather than failing the audit.
 */
class FailedJobsSource
{
   public const CHANNEL = 'failed-jobs';

   private const DATABASE_DRIVERS = ['database', 'database-uuids'];

   public function __construct(
      private readonly ErrorAudit $errorAudit,
      private readonly StackFrameNormaliser $frames,
   ) {}

   /**
    * @return Generator<int, LogEntry>
    */
   public function entries(Carbon $since, Carbon $until): Generator
   {
      if (! (bool) $this->errorAudit->value('failed_jobs.enabled', true)) {
         return;
      }

      $config = (array) config('queue.failed', []);
      $driver = $config['driver'] ?? (isset($config['table']) ? 'database' : null);
      $table = $config['table'] ?? null;

      if (! in_array($driver, self::DATABASE_DRIVERS, true) || ! is_string($table) || $table === '') {
         return;
      }

      try {
         $connection = DB::connection($config['database'] ?? null);

         if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return;
         }

         $rows = $connection->table($table)
            ->where('failed_at', '>=', $since)
            ->where('failed_at', '<=', $until)
            ->orderBy('failed_at')
            ->get(['connection', 'queue', 'payload', 'exception', 'failed_at']);
      } catch (Throwable) {
         return;
      }

      foreach ($rows as $row) {
         $entry = $this->toEntry($row);

         if ($entry !== null) {
            yield $entry;
         }
      }
   }

   private function toEntry(object $row): ?LogEntry
   {
      try {
         $failedAt = Carbon::parse($row->failed_at);
      } catch (Throwable) {
         return null;
      }

      [$exceptionClass, $message, $stackFrames, $appFrame] = $this->parseException((string) ($row->exception ?? ''));

      $jobName = $this->jobName((string) ($row->payload ?? ''));

      if ($jobName !== null) {
         $message = 'Job '.$jobName.' failed'.($message !== '' ? ': '.$message : '.');
      } elseif ($message === '') {
         return null;
      }

      return new LogEntry(
         loggedAt: $failedAt,
         level: LogLevelEnum::Error,
         channel: self::CHANNEL,
         environment: (string) config('app.env', 'production'),
         message: $message,
         exceptionClass: $exceptionClass,
         stackFrames: $stackFrames,
         appFrame: $appFrame,
      );
   }

   /**
    * The exception column holds PHP's string form of a Throwable:
    * "Class: message in /path/file.php:12" followed by "Stack trace:" lines.
    *
    * @return array{0: ?string, 1: string, 2: list<string>, 3: ?string}
    */
   private function parseException(string $text): array
   {
      $text = trim($text);

      if ($text === '') {
         return [null, '', [], null];
      }

      $head = $text;
      $traceStart = strpos($text, "\nStack trace:");

      if ($traceStart !== false) {
         $head = substr($text, 0, $traceStart);
      }

      $exceptionClass = null;
      $message = trim($head);

      if (preg_match('/^(?<class>\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*):\s(?<message>.*)$/s', $head, $matches) === 1) {
         $exceptionClass = ltrim($matches['class'], '\\');
         $message = trim($matches['message']);
      }

      $message = (string) preg_replace('/\s+in\s+\/\S+(?::\d+)?$/', '', $message);

      $maxFrames = $this->errorAudit->value('ai.max_stack_frames');
      $maxFrames = $maxFrames === null ? null : (int) $maxFrames;
      $stackFrames = [];
      $appFrame = null;

      if ($traceStart !== false) {
         foreach (explode("\n", substr($text, $traceStart)) as $line) {
            $frame = $this->frames->normalise($line);

            if ($frame === null) {
               continue;
            }

            if ($maxFrames === null || count($stackFrames) < $maxFrames) {
               $stackFrames[] = $frame;
            }

            if ($appFrame === null && $this->frames->isApplicationFrame($frame)) {
               $appFrame = $frame;
            }
         }
      }

      return [$exceptionClass, $message, $stackFrames, $appFrame];
   }

   private function jobName(string $payload): ?string
   {
      try {
         $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
      } catch (Throwable) {
         return null;
      }

      $name = $decoded['displayName'] ?? null;

      return is_string($name) && $name !== '' ? $name : null;
   }
}
