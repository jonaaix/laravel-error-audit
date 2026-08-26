<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Illuminate\Support\Carbon;

function group(?string $exceptionClass, string $normalizedMessage, LogLevelEnum $level = LogLevelEnum::Warning): IssueGroup
{
   $group = new IssueGroup('fp', $level, $exceptionClass, $normalizedMessage);

   $group->add(new LogEntry(
      loggedAt: Carbon::parse('2026-07-23 08:00:00'),
      level: $level,
      channel: 'daily',
      environment: 'production',
      message: $normalizedMessage,
      exceptionClass: $exceptionClass,
   ), '2026-07-23 08');

   return $group;
}

it('names an issue after its exception class when it has one', function (): void {
   expect(group('Illuminate\Database\QueryException', 'anything')->title())
      ->toBe('QueryException');
});

it('names a bare warning after its message instead of repeating the level', function (): void {
   expect(group(null, 'No shipping option for the given weight')->title())
      ->toBe('No shipping option for the given weight')
      ->not->toBe('WARNING');
});

it('truncates a long message rather than dropping it', function (): void {
   $title = group(null, str_repeat('a', 200))->title();

   expect(mb_strlen($title))->toBe(101)
      ->and($title)->toEndWith('…');
});

it('falls back to the level only when there is no message at all', function (): void {
   expect(group(null, '   ')->title())->toBe('WARNING');
});
