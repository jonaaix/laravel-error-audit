# Advanced

Nothing here is required to run the package. Reach for it when the defaults are
not enough.

## Runtime configuration

Config files are frozen once cached at deploy time. If your settings live in a
database, or an administrator picks the model, register a resolver — it is asked
on every read, so a change takes effect immediately.

```php
use Aaix\LaravelErrorAudit\Facades\ErrorAudit;

public function boot(): void
{
    ErrorAudit::recipientsUsing(fn () => Setting::get('audit_recipients'));
    ErrorAudit::modelUsing(fn () => Setting::get('audit_model'));
}
```

Named resolvers exist for the common keys (`recipientsUsing`, `senderUsing`,
`modelUsing`, `providerUsing`, `periodUsing`, `minimumLevelUsing`,
`contextLevelUsing`, `redactUsing`, `notifiableUsing`, `channelsUsing`). Anything
else is reachable by name, and a resolver returning `null` falls back to config:

```php
ErrorAudit::resolveUsing('ai.max_issues_per_run', fn () => $team->auditQuota());
```

## Delivery

The report is delivered as a **notification**, so routing is yours. Hand it any
notifiable — a user, a team, a collection of models:

```php
ErrorAudit::notifiableUsing(fn () => User::where('receives_audit', true)->get());
ErrorAudit::channelsUsing(fn () => ['mail', 'slack']);
```

The notification's `toArray()` carries the whole report — counts and every issue
with its urgency, cause and suggestion — so a custom channel can render it any
way it likes. To adjust the mail just before it goes out:

```php
ErrorAudit::sending(function ($mail, $report) {
    $mail->cc('archive@example.com');
});
```

## Filtering issues

Drop issues before they reach the analysis, and therefore before they cost
anything:

```php
ErrorAudit::filterIssues(
    fn ($issue) => $issue->exceptionClass !== NotFoundHttpException::class
);
```

## Log channels

Channels come from your application's own logging config:

- `single` and `daily` → resolved to their files
- `stack` → expanded; a file used by several channels is read once
- write-nowhere drivers (`slack`, `syslog`, `null`) → skipped

```php
'channels' => [
    'include' => ['*'],
    'exclude' => ['audit'],
],
```

Both lists accept wildcards; exclusion wins.

## Failed jobs

A job that exhausts its retries never reaches a log file — its exception lands
in the queue's failed-job store. Failed jobs from the audited period are
therefore included in the report like any other issue, under the `failed-jobs`
channel, with the job class carried in the message.

```php
'failed_jobs' => [
    'enabled' => true,
],
```

Strictly best effort: an application without a failed-job table, or with a
failed driver other than `database` / `database-uuids`, simply contributes
nothing — nothing fails, nothing warns.

## Queue

```php
'queue' => [
    'connection' => null,
    'queue' => null,
    'timeout' => 1800,
],
```

Left null, generation runs on the application's default connection. Name one
only to keep the audit off a busy queue.

## Assessment cache

```php
'cache' => [
    'store' => null,
],
```

Assessments live in the framework cache, keyed by fingerprint — no schema, no
migration. Name a store to keep the audit history out of a cache you flush
regularly. Losing the entries is harmless: each issue is simply treated as new
once and analysed again.

## Preview route

Off by default. The one deliberate environment switch in the package, so it
reads from `.env`, like `APP_DEBUG`:

```dotenv
ERROR_AUDIT_PREVIEW=true
```

```php
'preview' => [
    'enabled' => env('ERROR_AUDIT_PREVIEW', false),
    'path' => 'error-audit/preview',
    'middleware' => ['web'],
],
```

- `/error-audit/preview` renders the report exactly as it would be mailed.
- `/error-audit/preview?prompts=1` shows every prompt sent to the AI provider,
  with no API call made.

Both accept `?since=24+hours` and `?until=`. Keep it off in production, or guard
it with your own middleware.
