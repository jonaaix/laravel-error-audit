<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;


it('renders the preview for a valid period', function (): void {
   $this->get('/error-audit/preview?since=24+hours')
      ->assertOk()
      ->assertSee('Laravel Error Audit', false);
});

it('falls back to the default period instead of crashing on a malformed since', function (): void {
   $this->get('/error-audit/preview?since='.urlencode('24 hours?refresh=1'))
      ->assertOk()
      ->assertSee('Laravel Error Audit', false);
});

it('ignores a malformed until', function (): void {
   $this->get('/error-audit/preview?until=not-a-date')
      ->assertOk();
});

it('registers the named preview route', function (): void {
   expect(Route::has('error-audit.preview'))->toBeTrue();
});
