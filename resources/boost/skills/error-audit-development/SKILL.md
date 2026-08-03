---
name: error-audit-development
description: Set up and customise Laravel Error Audit — AI-assisted daily log audits by email, with the error-audit Artisan commands, runtime resolvers on the ErrorAudit facade, redaction and cost controls.
---

# Laravel Error Audit Development

## When to use this skill

Use this skill when working with the `aaix/laravel-error-audit` package — that is, when:

- Code imports or references `Aaix\LaravelErrorAudit\Facades\ErrorAudit`.
- The user asks for a daily/scheduled error report, log audit email, or AI assessment of log errors.
- The user runs or asks about any `error-audit:*` Artisan command.
- Redaction, recipients, provider/model choice or issue filtering for the audit report is being configured.

Do **not** invoke for live error tracking (Sentry, Flare, Telescope) or general log-viewer packages — this package generates an on-demand, batched report; it does not capture exceptions at runtime.

## Package conventions

- Namespace: `Aaix\LaravelErrorAudit`; service provider auto-registers the facade and both commands.
- Config file: `config/error-audit.php`, published via `php artisan vendor:publish --tag=error-audit-config`.
- Analysis runs on [`laravel/ai`](https://github.com/laravel/ai). Default: provider `gemini`, model `gemini-3.5-flash-lite`. Only the provider **API key** belongs in `.env`; provider and model choice are configuration.
- Log channels are **discovered** from the app's `logging.php` — every `single`, `daily` and `stack` channel resolved to its files. Non-file drivers (slack, syslog, …) are skipped automatically. Include/exclude via `channels.include` / `channels.exclude` (`'*'` = all).
- **Failed queue jobs** are included as issues too (channel `failed-jobs`), read from the queue's failed-job table — their exceptions never reach a log file. Best effort: apps without the table or with a non-database failed driver contribute nothing. Opt out via `failed_jobs.enabled`.
- Each distinct issue is fingerprinted, analysed **once**, and cached in the framework cache. No migrations, no schema — clearing the cache costs one re-analysis per issue.
- Redaction always runs and **cannot be disabled**; a sample that ends up mostly masked is discarded instead of sent.

## Artisan commands

| Command | Purpose |
| --- | --- |
| `error-audit:send` | Generate the report and mail it. Dispatches a queued job by default. |
| `error-audit:preview` | Print the exact redacted payloads that would go to the AI provider — **no API call**. |

### Flag cheatsheet

```
error-audit:send
  --since="24 hours"   # start of analysed period (relative or absolute date)
  --until=...          # end of period, defaults to now
  --sync               # build in-process instead of dispatching a job
  --dry-run            # build and print a summary, send no mail
  --refresh            # re-analyse issues that already have a cached assessment

error-audit:preview
  --since="24 hours"   # period to inspect
  --limit=5            # how many issue payloads to print
```

## Setup pattern

Two things are required: recipients and a provider key (for the default, a Gemini key configured for `laravel/ai`).

```php
// config/error-audit.php
'recipients' => ['ops@example.com'],
```

Then, **always in this order**:

```bash
php artisan error-audit:preview      # inspect what would leave the app — no API call
php artisan error-audit:send --sync --dry-run   # full run incl. AI, no mail
php artisan error-audit:send --sync  # first real report
```

There is no built-in schedule — the application owns the timing:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('error-audit:send')->dailyAt('07:00');
```

Do not add `--sync` to the scheduled call: without it the command dispatches a queued job, so a long run never blocks the scheduler.

## Runtime configuration — the `ErrorAudit` facade

Config files are frozen once cached. When a value lives in a database (settings table, admin-picked model), register a resolver in a service provider's `boot()`. Each resolver is asked first and **falls back to config when it returns `null`**.

```php
use Aaix\LaravelErrorAudit\Facades\ErrorAudit;

ErrorAudit::recipientsUsing(fn () => Setting::get('audit_recipients'));
ErrorAudit::modelUsing(fn () => Setting::get('audit_model'));

// Any config key, addressed as in the config file:
ErrorAudit::resolveUsing('ai.max_issues_per_run', fn () => 25);
```

Named shortcuts: `recipientsUsing`, `senderUsing`, `mailerUsing`, `providerUsing`, `modelUsing`, `periodUsing`, `minimumLevelUsing`, `contextLevelUsing`, `redactUsing`, plus `notifiableUsing` / `channelsUsing` for delivery.

### Delivery beyond plain addresses

```php
// Route to notifiables (user, team, collection) instead of config addresses
ErrorAudit::notifiableUsing(fn () => Team::admins()->get());

// Choose notification channels — mail, slack, custom
ErrorAudit::channelsUsing(fn () => ['mail', 'slack']);

// Adjust the mailable just before delivery
ErrorAudit::sending(function ($mail, $report) {
    $mail->subject('[myapp] '.$mail->subject);
});
```

### Filtering issues — before they cost anything

`filterIssues` drops issue groups **before** analysis, so filtered issues never reach the provider. Return `false` to discard.

```php
use Aaix\LaravelErrorAudit\Data\IssueGroup;

ErrorAudit::filterIssues(
    fn (IssueGroup $group) => $group->exceptionClass !== \App\Exceptions\KnownFlakyException::class
);
```

Useful `IssueGroup` members: `fingerprint`, `level`, `exceptionClass`, `normalizedMessage`, `count()`, `channels()`, `firstSeen()`, `lastSeen()`.

## Privacy and redaction

Log content (and, by default, the source files named in stack traces) is sent to a third-party AI provider. Rules:

- **Always run `error-audit:preview` before enabling** in a new project — it shows the exact outgoing payload without any API call.
- `redaction.context_level` controls eligibility: `class_only` (exception class + counts), `message_only` (+ redacted message), `full` (+ redacted stack frames, default).
- `ai.include_source_files` (default `true`) sends the app source files from stack traces along — sharpens analysis, but means **your code reaches the provider**. Set `false` if unacceptable.
- Extra project-specific patterns: `redaction.extra_patterns` in config (pattern => replacement) or at runtime via `ErrorAudit::redactUsing()`.

## Cost control

- Assessments are cached by fingerprint — recurring and already-known issues cost **zero** requests. Only use `--refresh` when the assessment logic or model changed.
- Caps live under `ai.*`. The default budget is monetary: `max_daily_cost_usd` (default `1.00`) sums actually billed cost per calendar day across all runs and stops new requests once spent. Structural caps `max_issues_per_run` / `max_input_tokens` default to `null` (off); `max_tokens_per_issue`, `samples_per_issue`, `max_source_file_bytes` bound the single request. Every cap is optional — `null` disables it, set caps all apply together.
- `minimum_level` (default `warning`) gates what is analysed at all; raise to `error` on noisy apps.

## Preview route (local layout tuning)

Opt-in browser route rendering the report exactly as mailed. It is a per-environment switch like `APP_DEBUG` — enable via `ERROR_AUDIT_PREVIEW=true` locally, **keep it off in production** or guard `preview.middleware`.

## Best-practice checklist

- ✅ Run `error-audit:preview` first in every new project — before the first real send.
- ✅ Schedule `error-audit:send` **without** `--sync`; the queued job keeps the scheduler free.
- ✅ Put only the provider API key in `.env`; provider/model belong in `config/error-audit.php`.
- ✅ Use `filterIssues()` for known-noisy exceptions — filtered issues never cost a request.
- ✅ Use runtime resolvers (`ErrorAudit::…Using()`) when values live in a database; keep static values in config.
- ✅ Register resolvers in a service provider's `boot()` method.
- ❌ Don't try to disable redaction — it always runs by design.
- ❌ Don't enable the preview route in production without your own auth middleware.
- ❌ Don't schedule `--refresh` runs — they re-bill every known issue on every run.
