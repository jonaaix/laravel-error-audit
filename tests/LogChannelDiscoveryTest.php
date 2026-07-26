<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Sources\LogChannelDiscovery;
use Illuminate\Config\Repository;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;

beforeEach(function (): void {
   $this->logDirectory = sys_get_temp_dir().'/error-audit-'.uniqid();
   mkdir($this->logDirectory);
});

afterEach(function (): void {
   array_map('unlink', glob($this->logDirectory.'/*') ?: []);
   rmdir($this->logDirectory);
});

function touchLog(string $directory, string $name): string
{
   $path = $directory.'/'.$name;
   file_put_contents($path, '');

   return $path;
}

function discover(array $channels, string $default = 'stack', array $selection = []): array
{
   $config = new Repository([
      'logging' => ['default' => $default, 'channels' => $channels],
      'error-audit' => ['channels' => $selection + ['include' => ['*'], 'exclude' => []]],
   ]);

   return (new LogChannelDiscovery($config))->discover();
}

it('resolves a single channel to its file', function (): void {
   $path = touchLog($this->logDirectory, 'laravel.log');

   $files = discover(['single' => ['driver' => 'single', 'path' => $path]], 'single');

   expect($files)->toHaveCount(1)
      ->and($files[0]->path)->toBe($path)
      ->and($files[0]->channel)->toBe('single');
});

it('expands a daily channel to its rotated files', function (): void {
   touchLog($this->logDirectory, 'laravel-2026-07-21.log');
   touchLog($this->logDirectory, 'laravel-2026-07-22.log');

   $files = discover([
      'daily' => ['driver' => 'daily', 'path' => $this->logDirectory.'/laravel.log'],
   ], 'daily');

   expect($files)->toHaveCount(2);
});

it('expands a stack channel into its file based members', function (): void {
   $path = touchLog($this->logDirectory, 'laravel.log');

   $files = discover([
      'stack' => ['driver' => 'stack', 'channels' => ['single', 'slack']],
      'single' => ['driver' => 'single', 'path' => $path],
      'slack' => ['driver' => 'slack', 'url' => 'https://example.test'],
   ]);

   expect($files)->toHaveCount(1)
      ->and($files[0]->channel)->toBe('single');
});

it('ignores channels that write nowhere readable', function (): void {
   $files = discover([
      'slack' => ['driver' => 'slack', 'url' => 'https://example.test'],
      'syslog' => ['driver' => 'syslog'],
      'errorlog' => ['driver' => 'errorlog'],
      'null' => ['driver' => 'monolog', 'handler' => SlackWebhookHandler::class],
   ], 'slack');

   expect($files)->toBeEmpty();
});

it('accepts file based monolog handlers', function (): void {
   $path = touchLog($this->logDirectory, 'custom.log');

   $files = discover([
      'custom' => [
         'driver' => 'monolog',
         'handler' => StreamHandler::class,
         'with' => ['stream' => $path],
      ],
   ], 'custom');

   expect($files)->toHaveCount(1)->and($files[0]->path)->toBe($path);
});

it('expands rotating monolog handlers like a daily channel', function (): void {
   touchLog($this->logDirectory, 'audit-2026-07-22.log');

   $files = discover([
      'audit' => [
         'driver' => 'monolog',
         'handler' => RotatingFileHandler::class,
         'with' => ['filename' => $this->logDirectory.'/audit.log'],
      ],
   ], 'audit');

   expect($files)->toHaveCount(1);
});

it('reports a shared file only once', function (): void {
   $path = touchLog($this->logDirectory, 'laravel.log');

   $files = discover([
      'first' => ['driver' => 'single', 'path' => $path],
      'second' => ['driver' => 'single', 'path' => $path],
   ], 'first');

   expect($files)->toHaveCount(1)->and($files[0]->channel)->toBe('first');
});

it('survives a stack that references itself', function (): void {
   $path = touchLog($this->logDirectory, 'laravel.log');

   $files = discover([
      'stack' => ['driver' => 'stack', 'channels' => ['nested']],
      'nested' => ['driver' => 'stack', 'channels' => ['stack', 'single']],
      'single' => ['driver' => 'single', 'path' => $path],
   ]);

   expect($files)->toHaveCount(1);
});

it('resolves the default channel before the others', function (): void {
   $first = touchLog($this->logDirectory, 'first.log');
   $second = touchLog($this->logDirectory, 'second.log');

   $files = discover([
      'alpha' => ['driver' => 'single', 'path' => $first],
      'omega' => ['driver' => 'single', 'path' => $second],
   ], 'omega');

   expect($files[0]->channel)->toBe('omega');
});

it('honours the exclude list', function (): void {
   $path = touchLog($this->logDirectory, 'laravel.log');

   $files = discover(
      ['queue' => ['driver' => 'single', 'path' => $path]],
      'queue',
      ['exclude' => ['queue']],
   );

   expect($files)->toBeEmpty();
});
