<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Parsing\LaravelLogParser;
use Aaix\LaravelErrorAudit\Sources\DiscoveredLogFile;
use Illuminate\Support\Carbon;

function parseLog(string $contents, ?Carbon $since = null, ?Carbon $until = null, ?LogLevelEnum $level = null): array
{
   $path = tempnam(sys_get_temp_dir(), 'audit');
   file_put_contents($path, $contents);

   $entries = iterator_to_array((new LaravelLogParser)->parse(
      new DiscoveredLogFile($path, 'daily'),
      $since ?? Carbon::parse('2000-01-01'),
      $until ?? Carbon::parse('2099-01-01'),
      $level ?? LogLevelEnum::Warning,
   ), false);

   unlink($path);

   return $entries;
}

it('parses the standard monolog line format', function (): void {
   $entries = parseLog('[2026-07-23 08:14:22] production.ERROR: Something broke');

   expect($entries)->toHaveCount(1)
      ->and($entries[0]->level)->toBe(LogLevelEnum::Error)
      ->and($entries[0]->environment)->toBe('production')
      ->and($entries[0]->message)->toBe('Something broke')
      ->and($entries[0]->loggedAt->toDateTimeString())->toBe('2026-07-23 08:14:22');
});

it('keeps entries apart when several follow each other', function (): void {
   $entries = parseLog(<<<'LOG'
   [2026-07-23 08:14:22] production.ERROR: First
   [2026-07-23 08:15:22] production.WARNING: Second
   LOG);

   expect($entries)->toHaveCount(2)
      ->and($entries[1]->level)->toBe(LogLevelEnum::Warning);
});

it('attaches trailing stack frames to the preceding entry', function (): void {
   $entries = parseLog(<<<'LOG'
   [2026-07-23 08:14:22] production.ERROR: Boom
   [stacktrace]
   #0 /app/app/Services/Thing.php(42): App\Services\Thing->explode(Object(Closure))
   #1 /app/vendor/laravel/framework/src/Foo.php(10): App\Services\Thing->run('secret')
   #2 {main}
   LOG);

   expect($entries)->toHaveCount(1)
      ->and($entries[0]->stackFrames)->toHaveCount(2)
      ->and($entries[0]->stackFrames[0])->toContain('Thing.php:42')
      ->and($entries[0]->stackFrames[0])->toContain('App\Services\Thing->explode()');
});

it('never carries stack frame arguments', function (): void {
   $entries = parseLog(<<<'LOG'
   [2026-07-23 08:14:22] production.ERROR: Boom
   #0 /app/app/Auth.php(9): App\Auth->login('user@example.com', 'hunter2')
   LOG);

   expect($entries[0]->stackFrames[0])->not->toContain('hunter2')
      ->and($entries[0]->stackFrames[0])->not->toContain('user@example.com')
      ->and($entries[0]->stackFrames[0])->toContain('App\Auth->login()');
});

it('reads the exception class out of the serialised context', function (): void {
   $entries = parseLog(
      '[2026-07-23 08:14:22] production.ERROR: getaddrinfo failed {"exception":"[object] (RedisException(code: 0): getaddrinfo failed at /app/vendor/foo.php:185)"}'
   );

   expect($entries[0]->exceptionClass)->toBe('RedisException');
});

it('unescapes namespaced exception classes from the context', function (): void {
   $entries = parseLog(
      '[2026-07-23 08:14:22] production.ERROR: SQL broke {"exception":"[object] (Illuminate\\\\Database\\\\QueryException(code: 42S02): nope)"}'
   );

   expect($entries[0]->exceptionClass)->toBe('Illuminate\Database\QueryException');
});

it('reads the exception class from a plain message prefix', function (): void {
   $entries = parseLog('[2026-07-23 08:14:22] production.ERROR: App\Exceptions\PaymentFailed: card declined');

   expect($entries[0]->exceptionClass)->toBe('App\Exceptions\PaymentFailed');
});

it('drops the serialised context from the message', function (): void {
   $entries = parseLog(
      '[2026-07-23 08:14:22] production.ERROR: Real message {"exception":"[object] (RedisException(code: 0): Real message at /app/foo.php:1)"}'
   );

   expect($entries[0]->message)->toBe('Real message');
});

it('skips entries below the minimum level', function (): void {
   $entries = parseLog(<<<'LOG'
   [2026-07-23 08:14:22] production.INFO: Noise
   [2026-07-23 08:14:23] production.DEBUG: More noise
   [2026-07-23 08:14:24] production.ERROR: Relevant
   LOG);

   expect($entries)->toHaveCount(1)->and($entries[0]->message)->toBe('Relevant');
});

it('honours the minimum level when raised to error', function (): void {
   $entries = parseLog(
      '[2026-07-23 08:14:22] production.WARNING: Just a warning',
      level: LogLevelEnum::Error,
   );

   expect($entries)->toBeEmpty();
});

it('skips entries outside the analysed window', function (): void {
   $entries = parseLog(
      "[2026-07-20 08:14:22] production.ERROR: Too old\n[2026-07-23 08:14:22] production.ERROR: Inside",
      since: Carbon::parse('2026-07-22 00:00:00'),
      until: Carbon::parse('2026-07-24 00:00:00'),
   );

   expect($entries)->toHaveCount(1)->and($entries[0]->message)->toBe('Inside');
});

it('tolerates a timestamp carrying a timezone offset', function (): void {
   $entries = parseLog('[2026-07-23T08:14:22.123456+02:00] production.ERROR: Offset');

   expect($entries)->toHaveCount(1);
});

it('ignores lines that are not log entries at all', function (): void {
   $entries = parseLog("garbage without a header\nmore garbage");

   expect($entries)->toBeEmpty();
});
