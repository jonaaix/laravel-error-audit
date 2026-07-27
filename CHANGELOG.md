# Changelog

All notable changes to `aaix/laravel-error-audit` are documented here.

## [1.2.0] - 2026-07-27

### Changed

- A synchronous run (`error-audit:send --sync` / `--dry-run`) now narrates its
  progress on the console instead of hiding behind a single spinner: one line
  per phase (collecting, previous period, analysis, chart, sending) with counts,
  and one line per issue stating what happened to it — analysed with its cost,
  served from cache, skipped by the budget, or failed. Queued runs are
  unaffected. Applications embedding the package can implement the new
  `AuditProgress` contract to receive the same signals.

## [1.1.0] - 2026-07-27

### Added

- Failed queue jobs are included in the report as issues. Their exceptions
  never reach a log file — they are read from the queue's failed-job store
  (`queue.failed`) instead, appear under the `failed-jobs` channel with the job
  class in the message, and flow through the same fingerprinting, redaction and
  assessment as log entries. Best effort by design: applications without a
  failed-job table, or with a driver other than `database` / `database-uuids`,
  contribute nothing. Opt out via `failed_jobs.enabled`.
- The issue list in the report email is now sectioned per channel: a divider
  names the channel (or the queue) before its cards, so the reader always knows
  where they are while scrolling. Dividers only appear when a report spans more
  than one channel. Failed-job issues additionally carry a QUEUE badge on the
  card itself. Both mail variants (HTML and plain text) are sectioned alike.

## [1.0.2] - 2026-07-27

### Fixed

- Report delivery to the configured recipient addresses crashed with
  "An email must have a To, Cc, or Bcc header": Laravel's MailChannel sends a
  Mailable returned from `toMail()` as-is and never applies the notifiable's
  mail route. The recipients are now copied onto the mailable itself. Delivery
  via `notifiableUsing()` was unaffected.

## [1.0.1] - 2026-07-27

### Changed

- Default `ai.max_input_tokens` corrected from 500000 to the intended 40000.
  The cumulative per-run input budget now matches the code fallback; with the
  default 20k per-issue cap, two maximally sized issues fill the window.

## [1.0.0] - 2026-07-26

### Added

- Log channel discovery from the application's own logging configuration,
  covering `single`, `daily`, nested `stack` and file based `monolog` channels.
- Streaming parser for the standard Monolog line format, including multi-line
  stack traces and exception classes carried in the serialised context.
- Fingerprint based grouping that normalises ids, uuids, paths, timestamps and
  addresses so one cause forms one group.
- Redaction layer with a fixed pattern list, a Shannon entropy detector for
  unanticipated credential formats, and fail-closed discarding of samples that
  end up mostly masked. It cannot be disabled.
- AI assessment of each distinct issue exactly once, cached by fingerprint
  across runs, with caps on issue count, per-run and per-prompt input tokens,
  and a bounded response. Ships with `gemini-3.5-flash-lite` as a working
  default; required output fields guarantee every assessment is complete.
- The application's own source files named in a stack trace are sent with the
  error by default, so the model reads the code at the failure site. Strictly
  whitelisted to the project's own directories, redacted, opt-out via
  `include_source_files`.
- Responsive report email built on Laravel's own mail components with a
  packaged theme, per-level summary tiles, issue cards, a stacked bar timeline
  chart rendered with GD (no browser required), and a plain text alternative.
- Delivery through Laravel's notification layer: route the report to any
  notifiable and any channels; runtime resolvers for every setting so values can
  come from a database rather than frozen config.
- `error-audit:send` and `error-audit:preview` commands, an opt-in browser
  preview route (including a `?prompts=1` view of the exact prompts), plus
  `GenerateErrorAuditJob` for queued generation.
