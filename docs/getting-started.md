# Getting Started

Everything you need to run the package is on this page. The rest is reference.

## Install

```bash
composer require aaix/laravel-error-audit
```

```bash
php artisan vendor:publish --tag=error-audit-config
```

## Configure

Two things are required.

**1. Recipients** — where the report goes, in this package's config file:

```php
// config/error-audit.php
'recipients' => ['you@example.com'],
```

**2. A provider key** — the analysis runs on
[`laravel/ai`](https://github.com/laravel/ai); set up a provider key there (for
the default, a Gemini key).

With both in place the package works out of the box on `gemini-3.5-flash-lite`;
change provider or model in [Configuration](/configuration).

## Look before you leap

Log lines and your source files are about to go to a third party. See exactly
what — this makes **no** API call:

```bash
php artisan error-audit:preview
```

It prints the redacted payload for the most frequent issues. What leaves and how
it is masked: [Privacy](/privacy).

## Send the first report

```bash
php artisan error-audit:send --sync
```

`--sync` builds the report in the current process instead of dispatching a job.
Add `--dry-run` to build it and print a summary without sending mail.

## Schedule it

There is no built-in schedule — the report is generated on demand, so the timing
is yours:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('error-audit:send')->dailyAt('07:00');
```

Without `--sync` the command dispatches a queued job, so a long run never blocks
the scheduler.
