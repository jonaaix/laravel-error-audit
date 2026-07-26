<p align="center">
  <a href="https://github.com/jonaaix/laravel-error-audit">
    <img src="https://jonaaix.github.io/laravel-error-audit/logo.svg" alt="Laravel Error Audit Logo" width="120">
  </a>
</p>

<h1 align="center">Laravel Error Audit</h1>

<p align="center">
AI-assisted daily audit of your Laravel log files, delivered by email.
</p>

<p align="center">
  <a href="https://packagist.org/packages/aaix/laravel-error-audit"><img src="https://img.shields.io/packagist/v/aaix/laravel-error-audit.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/aaix/laravel-error-audit"><img src="https://img.shields.io/packagist/dt/aaix/laravel-error-audit.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://github.com/jonaaix/laravel-error-audit/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/jonaaix/laravel-error-audit/tests.yml?branch=main&label=tests&style=flat-square" alt="GitHub Actions"></a>
  <a href="https://github.com/jonaaix/laravel-error-audit/blob/main/LICENSE.md"><img src="https://img.shields.io/packagist/l/aaix/laravel-error-audit.svg?style=flat-square" alt="License"></a>
</p>

---

Sentry, Kibana and their peers are built for live monitoring at scale. Most applications do not need that. They need a short,
honest answer to one question every morning: **what broke yesterday, and does any of it belong in today's plan?**

This package answers it. It reads every file based log channel your application defines, groups errors and warnings by cause,
has an AI model assess each distinct issue once, and mails you the result.

## Installation

Install the package via Composer:

```bash
composer require aaix/laravel-error-audit
```

Next, publish the configuration file. This is optional but recommended.

```bash
php artisan vendor:publish --provider="Aaix\LaravelErrorAudit\ErrorAuditServiceProvider" --tag="error-audit-config"
```

## A Quick Look

**Schedule the daily report**

Report generation is on demand — you own the schedule. The command dispatches a queued job by default, so a long run never
blocks the scheduler:

```php
Schedule::command('error-audit:send')->dailyAt('07:00');
```

**Preview before anything leaves your application**

Log content is sent to a third party AI provider. Inspect exactly what would be sent first — this makes no API call:

```bash
php artisan error-audit:preview
```

**Run it manually**

```bash
php artisan error-audit:send --since="48 hours" --dry-run
```

## How it works

1. **Discover** — every `single`, `daily` and `stack` channel in your logging configuration is resolved to the files it
   actually writes to. Slack, syslog and other non file drivers are skipped.
2. **Group** — entries are fingerprinted so the same failure lands in one group regardless of the ids, paths, timestamps or
   addresses it carries.
3. **Redact** — credentials, tokens, personal data and generated-looking strings are masked before anything leaves the
   application. A sample that ends up mostly masked is discarded rather than sent half redacted.
4. **Assess** — each distinct issue is analysed **once** and cached by fingerprint. Ten thousand occurrences of the same
   exception cost one request; an issue already assessed yesterday costs nothing at all.
5. **Deliver** — a responsive email built from Laravel's own mail components, with counters, a timeline chart and one card per
   issue sorted by urgency.

### Documentation

For the full documentation, including every configuration key and the complete redaction pattern list, please visit our full
[documentation website](https://jonaaix.github.io/laravel-error-audit).
