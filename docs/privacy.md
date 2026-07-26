# Privacy

Log content — and, by default, your own source files — is sent to a third-party
AI provider. This page is what leaves, and what protects it.

## Redaction

**Always on, cannot be disabled.** Masked before anything leaves:

- Emails, `Authorization` headers, API keys and tokens
- Database DSNs, session and cookie values, JWTs
- Cloud credentials, private keys, card numbers, IBANs
- Any long high-entropy string — credential formats no list anticipates

Two more guarantees: a sample more than half masked is **discarded** rather than
sent half-redacted, and stack frames keep only file, line and `Class::method` —
argument values never survive parsing.

## What is transmitted

```php
'context_level' => 'full',
// 'class_only' | 'message_only' | 'full'
```

| Value | Transmitted |
|---|---|
| `class_only` | exception class, level, channel, counts |
| `message_only` | adds the redacted message |
| `full` | adds the redacted stack trace and source files |

`full` is the default — from `50× QueryException` alone no model can infer a
cause. `class_only` and `message_only` remain for regulated environments.

## Source files

By default the source files named in a stack trace are sent, so the model reads
the code at the failure site instead of guessing. It is the single biggest lever
on analysis quality.

```php
'ai' => [
    'include_source_files' => true,
    'max_source_file_bytes' => 200000,
],
```

- Only your own directories are read: `app`, `packages`, `routes`, `config`,
  `database`, `bootstrap`, `tests` — vendor and anything outside the project is refused.
- Every file passes the same redaction as the logs.
- Not acceptable? Set `include_source_files` to `false`.

`php artisan error-audit:preview` shows exactly which files would be sent.

## Your obligations

- **Check data retention with your provider** and enable zero data retention
  where offered — it matters more once source files are in the payload.
- **A data processing agreement** is required under Art. 28 GDPR before
  production use.

## Custom patterns

```php
'redaction' => [
    'extra_patterns' => [
        '/\bCUST-\d+\b/' => '{customer}',
    ],
],
```
