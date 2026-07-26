# Laravel Error Audit

AI-assisted daily audit of your Laravel log files, delivered by email.

Sentry, Kibana and their peers are built for live monitoring at scale. Most
applications do not need that. They need a short, honest answer to one question
every morning: **what broke yesterday, and does any of it belong in today's
plan?**

This package answers it. It reads every file based log channel your application
defines, groups errors and warnings by cause, has an AI model assess each
distinct issue once, and mails you the result.

```bash
composer require aaix/laravel-error-audit
```

## How it works

1. **Discover** — every `single`, `daily` and `stack` channel in your logging
   configuration is resolved to the files it actually writes to. Slack, syslog
   and other non file drivers are skipped.
2. **Group** — entries are fingerprinted so the same failure lands in one group
   regardless of the ids, paths, timestamps or addresses it carries.
3. **Redact** — credentials, tokens, personal data and generated-looking
   strings are masked before anything leaves the application. A sample that ends
   up mostly masked is discarded rather than sent half redacted.
4. **Assess** — each distinct issue is analysed **once** and cached by
   fingerprint. Ten thousand occurrences of the same exception cost one request;
   an issue already assessed yesterday costs nothing at all.
5. **Deliver** — a responsive email built from Laravel's own mail components,
   with counters, a timeline chart and one card per issue sorted by urgency.

## Scheduling

Report generation is on demand. You own the schedule:

```php
Schedule::command('error-audit:send')->dailyAt('07:00');
```

The command dispatches a queued job by default, so a long run never blocks the
scheduler.

## Before you enable it

Log content is sent to a third party AI provider. Inspect exactly what would
leave your application first — this makes no API call:

```bash
php artisan error-audit:preview
```

## Documentation

Full documentation, including every configuration key and the complete
redaction pattern list: **https://aaix.github.io/laravel-error-audit**

## License

MIT.
