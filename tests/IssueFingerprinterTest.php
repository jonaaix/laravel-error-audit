<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Grouping\IssueFingerprinter;
use Illuminate\Support\Carbon;

function entry(string $message, ?string $exceptionClass = null, array $frames = []): LogEntry
{
   return new LogEntry(
      loggedAt: Carbon::parse('2026-07-23 08:00:00'),
      level: LogLevelEnum::Error,
      channel: 'daily',
      environment: 'production',
      message: $message,
      exceptionClass: $exceptionClass,
      stackFrames: $frames,
   );
}

it('groups the same failure despite differing identifiers', function (string $a, string $b): void {
   $fingerprinter = new IssueFingerprinter;

   expect($fingerprinter->fingerprint(entry($a)))->toBe($fingerprinter->fingerprint(entry($b)));
})->with([
   'record ids' => [
      'No query results for model [App\Models\Order] 4711',
      'No query results for model [App\Models\Order] 9182',
   ],
   'uuids' => [
      'Job 3f2504e0-4f89-11d3-9a0c-0305e82c3301 failed',
      'Job 7c9e6679-7425-40de-944b-e07fc1f90ae7 failed',
   ],
   'timestamps' => [
      'select * from sessions where last_seen_at < 2026-07-14 08:58:01',
      'select * from sessions where last_seen_at < 2026-07-15 11:02:44',
   ],
   'file paths' => [
      'Could not read /var/www/storage/app/imports/a/b.csv',
      'Could not read /var/www/storage/app/imports/c/d.csv',
   ],
   'email addresses' => [
      'Delivery to jonas@example.com failed',
      'Delivery to anna@example.org failed',
   ],
   'serialised payloads carrying a timestamp' => [
      'No shipping option {"id":10,"name":"Up to 10kg","created_at":"2026-08-17T02:43:56.000000Z"} for size {"id":1,"name":"Small"}',
      'No shipping option {"id":23,"name":"Up to 30kg","created_at":"2026-08-19T11:07:02.000000Z"} for size {"id":4,"name":"Large"}',
   ],
]);

it('stops the timestamp pattern at the timestamp', function (string $message, string $expected): void {
   expect((new IssueFingerprinter)->signature($message))->toBe($expected);
})->with([
   'space separated' => ['failed at 2026-08-17 02:43:56 exactly', 'failed at {timestamp} exactly'],
   'fractional seconds' => ['failed at 2026-08-17T02:43:56.000000Z exactly', 'failed at {timestamp} exactly'],
   'utc designator' => ['failed at 2026-08-17T02:43:56Z exactly', 'failed at {timestamp} exactly'],
   'numeric offset' => ['failed at 2026-08-17T02:43:56+02:00 exactly', 'failed at {timestamp} exactly'],
   // The trailing quote must survive, or the later quote pass pairs across it.
   'quoted inside a payload' => ['{"at":"2026-08-17T02:43:56.000000Z","size":"Small"}', '{"{value}":"{value}","{value}":"{value}"}'],
]);

it('keeps genuinely different failures apart', function (): void {
   $fingerprinter = new IssueFingerprinter;

   expect($fingerprinter->fingerprint(entry('Connection refused')))
      ->not->toBe($fingerprinter->fingerprint(entry('Permission denied')));
});

it('separates identical messages thrown by different exception classes', function (): void {
   $fingerprinter = new IssueFingerprinter;

   expect($fingerprinter->fingerprint(entry('timed out', 'RedisException')))
      ->not->toBe($fingerprinter->fingerprint(entry('timed out', 'PDOException')));
});

it('separates the same exception raised from different application frames', function (): void {
   $fingerprinter = new IssueFingerprinter;

   $first = entry('timed out', 'RedisException', ['app/Services/Import.php:12 Import->run()']);
   $second = entry('timed out', 'RedisException', ['app/Services/Export.php:44 Export->run()']);

   expect($fingerprinter->fingerprint($first))->not->toBe($fingerprinter->fingerprint($second));
});

it('prefers the first application frame over vendor noise', function (): void {
   $fingerprinter = new IssueFingerprinter;

   $first = entry('boom', 'RuntimeException', [
      'vendor/laravel/framework/src/Foo.php:10 Foo->a()',
      'app/Services/Import.php:12 Import->run()',
   ]);

   $second = entry('boom', 'RuntimeException', [
      'vendor/laravel/framework/src/Bar.php:99 Bar->b()',
      'app/Services/Import.php:12 Import->run()',
   ]);

   expect($fingerprinter->fingerprint($first))->toBe($fingerprinter->fingerprint($second));
});
