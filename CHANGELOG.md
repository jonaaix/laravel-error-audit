# Changelog

All notable changes to `aaix/laravel-error-audit` are documented here.

## [1.6.3] - 2026-07-27

### Fixed

- The analysis footnote lost its token line on fully cached runs and ran its
  sentences together without spaces. It is now assembled as one string shared
  by both mail variants: the token line always appears when a budget exists
  (reading "~0 of 40,000 tokens" when everything came from cache), with a
  proper space between every sentence.

## [1.6.2] - 2026-07-27

### Fixed

- The report footer counted only freshly requested analyses, so a fully
  cached run read "0 of 25 issue types analysed by AI" — plainly wrong. A
  cached assessment is an analysed issue; the count now covers every issue
  carrying an assessment, and the stated cost is the sum of what those
  assessments truly cost, whether paid this run or on an earlier one.

### Changed

- The chart carries two value axes: errors on the left in red, warnings on the
  right in amber, each scaled to its own tallest stack. A day with 49 errors
  no longer flatlines beside 7,400 warnings — both series use the full plot
  height. Each axis is titled with its level name in the series colour, so
  there is never a doubt which scale measures which bars.

## [1.6.1] - 2026-07-27

### Changed

- The chart's vertical axis now tops out at the tallest stack instead of a
  rounded-up "nice" ceiling that could waste up to half the plot as empty
  headroom; axis labels carry thousands separators.
- Chart colours are vivid now — saturated red for errors, bright amber for
  warnings — so the two levels are distinguishable at a glance instead of
  blurring into neighbouring earth tones.

## [1.6.0] - 2026-07-27

### Changed

- Cause and Suggestion on an issue card are stacked — label above text —
  instead of a two-column table. Reads better on narrow clients and wastes no
  width on a label column.

## [1.5.0] - 2026-07-27

### Changed

- New subject line format: date first, then the two counts, then the
  application — `27.07. — 49 ERRORS · 82 WARNINGS — WSU_eBusiness`. The
  urgency symbol and the new-issue count no longer appear in the subject.
- A period without a single error or warning no longer sends a mail. Opt into
  an "all clear" heartbeat via the new `send_empty_reports` config key.

## [1.4.0] - 2026-07-27

### Changed

- The report is now a pure function of the analysed time window: running the
  same period twice yields the identical report. "New" and the per-issue
  previous count compare against the immediately preceding window of the same
  length, read fresh from the logs — no longer against state left behind by
  earlier runs. The assessment store shrank to what it always should have
  been: a cache of AI assessments that saves requests without changing what
  the report says.

### Removed

- `AuditedIssue::$daysOpen` — run-history bookkeeping that was displayed
  nowhere.

## [1.3.0] - 2026-07-27

### Changed

- Default `ai.max_issues_per_run` raised from 15 to 100. With per-issue caps
  and the assessment cache in place, the issue counter rarely needs to be the
  limiting factor; the token budget remains the effective backstop.

### Added

- The report states how much of the input token budget the analysis used, so
  it is visible which cap — issue count or tokens — a run is approaching. Shown
  in the mail footer ("Input used: ~12,840 of 40,000 tokens."), the plain text
  variant, the CLI summary table and as a detail line in synchronous runs.

## [1.2.1] - 2026-07-27

### Changed

- The channel divider in the report email is now a filled, full-width bar —
  dark for log channels, indigo for the queue section — instead of a thin
  underline that was easy to scroll past.
- `demo.html` in the repository root is a committed, always-current preview of
  the report email, rewritten on every test run; `composer demo` regenerates it
  alone. A `.gitattributes` keeps the demo, docs and tests out of the composer
  dist.

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
