---
layout: home

hero:
  name: Laravel Error Audit
  text: Your logs, read for you.
  tagline: An AI-assisted audit of yesterday's errors and warnings, in your inbox before you open the editor.
  image:
    src: /logo.svg
    alt: Laravel Error Audit
  actions:
    - theme: brand
      text: Get Started
      link: /getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/jonaaix/laravel-error-audit
---

```bash
composer require aaix/laravel-error-audit
```

## What lands in your inbox

<a href="/laravel-error-audit/audit-mail.png" target="_blank" rel="noreferrer">
  <img src="/audit-mail.png" alt="The daily error audit email" width="240" style="float: right; width: 240px; margin: 0.25rem 0 1rem 1.5rem; border-radius: 10px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);" />
</a>

**A status word and three numbers.** How many errors, how many warnings, and how
many distinct issue types occurred. The subject line carries the same signal, so
you know the answer from the lock screen.

**A timeline.** A spike at 03:00 tells a different story than an even spread or
a wall starting at 14:20. Errors and warnings stacked by issue type, rendered to
an image so it shows in every mail client.

**One card per issue, sorted by urgency.** A plain-language title, the likely
cause, and a suggestion for confirming it — read from your own source code where
the failure occurred, not guessed from a stack trace you decode over coffee.

## What it costs

**A cent a day.** The exact figure prints at the foot of every email.

## What leaves your application

The log lines and, by default, the source files named in each stack trace.
Everything is redacted first, and the redaction layer cannot be switched off.
See exactly what would be sent — and how it is masked — before enabling anything:

```bash
php artisan error-audit:preview
```

No API call is made; what you see is what would be sent. Details in
[Privacy](/privacy).
