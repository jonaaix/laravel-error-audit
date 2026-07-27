<?php

declare(strict_types=1);

it('narrates a synchronous run phase by phase', function (): void {
   config()->set('error-audit.recipients', ['ops@example.com']);

   $this->artisan('error-audit:send', ['--sync' => true, '--dry-run' => true])
      ->expectsOutputToContain('Collecting entries — log channels and failed jobs')
      ->expectsOutputToContain('Reading the preceding period for the change rate')
      ->expectsOutputToContain('Analysing issues')
      ->expectsOutputToContain('Rendering the timeline chart')
      ->expectsOutputToContain('Dry run — no mail was sent.')
      ->assertSuccessful();
});

it('stays silent about phases when the run is queued', function (): void {
   config()->set('error-audit.recipients', ['ops@example.com']);
   \Illuminate\Support\Facades\Queue::fake();

   $this->artisan('error-audit:send')
      ->expectsOutputToContain('Error audit queued.')
      ->doesntExpectOutputToContain('Collecting entries')
      ->assertSuccessful();
});
