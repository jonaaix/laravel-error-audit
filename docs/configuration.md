# Configuration

```bash
php artisan vendor:publish --tag=error-audit-config
```

Everything below is a plain value in `config/error-audit.php` — none of it is a
credential. The provider's API key is `laravel/ai`'s, configured over there.

## Period and level

```php
'period' => '24 hours',
'minimum_level' => 'warning',
```

`period` is any relative date string. `minimum_level` is the floor — `warning`
includes warnings, errors, critical, alert and emergency.

## Provider and model

```php
'ai' => [
    'provider' => 'gemini',
    'model' => 'gemini-3.5-flash-lite',
    'timeout' => 120,
],
```

Ships with Gemini's cheapest text model — a fraction of a cent per issue. Both
settings accept any provider and model `laravel/ai` knows (OpenAI, Anthropic, …).

## Cost control

```php
'ai' => [
    'max_issues_per_run' => 100,
    'max_input_tokens' => 40000,
    'max_tokens_per_issue' => 20000,
    'samples_per_issue' => 1,
    'max_sample_characters' => null,
    'max_stack_frames' => null,
],
```

Cost is kept in check by structure, not by trimming context:

- **One request per distinct issue** — ten thousand copies of the same
  exception cost one analysis.
- **Cached by fingerprint** — a recurring issue costs nothing on later runs.
  Force re-analysis with `--refresh`.
- **`max_issues_per_run`** caps how many distinct issues are sent, most frequent
  first; **`max_input_tokens`** is a hard ceiling on the whole run.
- **`max_tokens_per_issue`** bounds a single prompt (~20k is generous). If an
  issue's source files would push it over, the least-essential are dropped first
  — the message and stack always go — and the report notes what was omitted.

The response is bounded too: the model may return at most 8k tokens, ample for
the short fields it fills in. Skipped issues still appear in the report with
their counts — the footer states how many of the total were analysed.

## Delivery

```php
'recipients' => ['ops@example.com'],
'from' => ['address' => null, 'name' => null],
'mailer' => null,
```

Recipients are comma-friendly and can also come from a database or any
notifiable — see [Advanced](/advanced#delivery). Leave `mailer` null to use the
application default.

## Chart

Mail clients run no JavaScript, so the timeline is a PNG drawn with `ext-gd`. No
GD, no chart — the report still goes out.

```php
'chart' => [
    'width' => 600,
    'height' => 200,
    'font_path' => null,
],
```

Buckets follow the period: hourly up to two days, daily beyond.

---

Redaction and what leaves your application are covered in [Privacy](/privacy);
runtime resolvers, custom channels, filtering, queue and the preview route in
[Advanced](/advanced).
