<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Sources\FailedJobsSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createFailedJobsTable(): void
{
   Schema::create('failed_jobs', function ($table): void {
      $table->id();
      $table->string('uuid')->unique();
      $table->text('connection');
      $table->text('queue');
      $table->longText('payload');
      $table->longText('exception');
      $table->timestamp('failed_at')->useCurrent();
   });
}

function insertFailedJob(array $overrides = []): void
{
   DB::table('failed_jobs')->insert(array_merge([
      'uuid' => (string) Str::uuid(),
      'connection' => 'redis',
      'queue' => 'default',
      'payload' => json_encode(['displayName' => 'App\\Jobs\\SyncOrders']),
      'exception' => implode("\n", [
         'RuntimeException: Order 4711 no longer exists in /var/www/app/Jobs/SyncOrders.php:52',
         'Stack trace:',
         '#0 /var/www/vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php(102): App\\Jobs\\SyncOrders->handle()',
         '#1 /var/www/app/Jobs/SyncOrders.php(52): App\\Jobs\\SyncOrders->sync(4711)',
         '#2 {main}',
      ]),
      'failed_at' => '2026-07-26 03:15:00',
   ], $overrides));
}

beforeEach(function (): void {
   config()->set('queue.failed', [
      'driver' => 'database-uuids',
      'database' => 'testing',
      'table' => 'failed_jobs',
   ]);

   $this->source = app(FailedJobsSource::class);
   $this->window = [Carbon::parse('2026-07-25 07:00:00'), Carbon::parse('2026-07-26 07:00:00')];
});

it('turns a failed job into a log entry with class, job name and frames', function (): void {
   createFailedJobsTable();
   insertFailedJob();

   $entries = iterator_to_array($this->source->entries(...$this->window));

   expect($entries)->toHaveCount(1)
      ->and($entries[0]->level)->toBe(LogLevelEnum::Error)
      ->and($entries[0]->channel)->toBe('failed-jobs')
      ->and($entries[0]->exceptionClass)->toBe('RuntimeException')
      ->and($entries[0]->message)->toBe('Job App\Jobs\SyncOrders failed: Order 4711 no longer exists')
      ->and($entries[0]->stackFrames)->toHaveCount(2)
      ->and($entries[0]->loggedAt->toDateTimeString())->toBe('2026-07-26 03:15:00');
});

it('only reads failed jobs inside the audited period', function (): void {
   createFailedJobsTable();
   insertFailedJob(['failed_at' => '2026-07-26 03:15:00', 'uuid' => 'inside']);
   insertFailedJob(['failed_at' => '2026-07-20 03:15:00', 'uuid' => 'before']);
   insertFailedJob(['failed_at' => '2026-07-26 09:00:00', 'uuid' => 'after']);

   $entries = iterator_to_array($this->source->entries(...$this->window));

   expect($entries)->toHaveCount(1);
});

it('contributes nothing when the failed jobs table does not exist', function (): void {
   $entries = iterator_to_array($this->source->entries(...$this->window));

   expect($entries)->toBe([]);
});

it('contributes nothing when the failed job driver is not database backed', function (): void {
   createFailedJobsTable();
   insertFailedJob();

   config()->set('queue.failed', ['driver' => 'dynamodb', 'table' => 'failed_jobs']);

   $entries = iterator_to_array($this->source->entries(...$this->window));

   expect($entries)->toBe([]);
});

it('can be switched off in the configuration', function (): void {
   createFailedJobsTable();
   insertFailedJob();

   config()->set('error-audit.failed_jobs.enabled', false);

   $entries = iterator_to_array($this->source->entries(...$this->window));

   expect($entries)->toBe([]);
});

it('survives an exception column without the expected shape', function (): void {
   createFailedJobsTable();
   insertFailedJob(['exception' => 'something went terribly wrong', 'payload' => json_encode(['displayName' => 'App\\Jobs\\Nightly'])]);

   $entries = iterator_to_array($this->source->entries(...$this->window));

   expect($entries)->toHaveCount(1)
      ->and($entries[0]->exceptionClass)->toBeNull()
      ->and($entries[0]->message)->toBe('Job App\Jobs\Nightly failed: something went terribly wrong');
});
