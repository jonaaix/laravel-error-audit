<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Enums\ContextLevelEnum;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;

return [

   /*
   |--------------------------------------------------------------------------
   | Recipients
   |--------------------------------------------------------------------------
   |
   | Addresses the audit report is delivered to. List them here, or resolve
   | them at runtime with ErrorAudit::recipientsUsing() when they live in a
   | database rather than in a file.
   |
   */

   'recipients' => [],

   'from' => [
      'address' => null,
      'name' => null,
   ],

   'mailer' => null,

   /*
   |--------------------------------------------------------------------------
   | Analysis Period
   |--------------------------------------------------------------------------
   |
   | How far back a report looks by default, expressed as a relative date
   | string. The report is generated on demand, so the delivery schedule is
   | owned by the application, not by this package.
   |
   */

   'period' => '24 hours',

   'minimum_level' => LogLevelEnum::Warning->value,

   /*
   |--------------------------------------------------------------------------
   | Log Channels
   |--------------------------------------------------------------------------
   |
   | Channels are discovered from the application's logging configuration.
   | Only file based channels are considered; slack, syslog and comparable
   | drivers are ignored. Use "*" to include every discovered channel.
   |
   */

   'channels' => [
      'include' => ['*'],
      'exclude' => [],
   ],

   /*
   |--------------------------------------------------------------------------
   | Artificial Intelligence
   |--------------------------------------------------------------------------
   |
   | The package ships a working default: Gemini's cheapest text model, which is
   | more than enough for classifying an error and writing a few short fields.
   | Point it at any provider and model laravel/ai supports to change it. Only
   | the provider's API key belongs in the environment; the choice of provider
   | and model is configuration and lives here.
   |
   | Each distinct issue is analysed once and cached by fingerprint, so
   | recurring issues never cost a second request.
   |
   */

   'ai' => [
      'enabled' => true,
      'provider' => 'gemini',
      'model' => 'gemini-3.5-flash-lite',
      'timeout' => 120,

      'max_issues_per_run' => 15,
      'max_input_tokens' => 40000,
      'max_tokens_per_issue' => 20000,
      'samples_per_issue' => 1,
      'max_sample_characters' => null,
      'max_stack_frames' => null,

      // Send the application source files named in a stack trace along with the
      // error. It sharpens the analysis considerably — the model reads the code
      // at the failure site instead of guessing at it.
      //
      // NOTE: this means your own source code reaches the configured AI provider.
      // Redaction still runs over every file, but if that is unacceptable in your
      // environment, set this to false. Run "php artisan error-audit:preview" to
      // see exactly which files would be sent.
      'include_source_files' => true,
      'max_source_file_bytes' => 200000,
   ],

   /*
   |--------------------------------------------------------------------------
   | Redaction
   |--------------------------------------------------------------------------
   |
   | Log content is sent to a third party provider, so redaction always runs
   | and cannot be disabled. "context_level" controls how much of an entry is
   | eligible for transmission in the first place:
   |
   |   class_only   - exception class and counts only
   |   message_only - adds the redacted message
   |   full         - adds redacted stack frames
   |
   | Run "php artisan error-audit:preview" to inspect the exact payload that
   | would leave the application, without performing a single API call.
   |
   */

   'redaction' => [
      'context_level' => ContextLevelEnum::Full->value,
      'entropy_threshold' => 24,
      'discard_above_masked_ratio' => 0.5,
      'extra_patterns' => [],
   ],

   /*
   |--------------------------------------------------------------------------
   | Chart
   |--------------------------------------------------------------------------
   |
   | Mail clients run no JavaScript, so the timeline is drawn straight to a PNG
   | with ext-gd before the report is sent. If GD is unavailable the report
   | simply goes out without a chart rather than failing.
   |
   */

   'chart' => [
      'width' => 600,
      'height' => 200,
      'font_path' => null,
   ],

   /*
   |--------------------------------------------------------------------------
   | Preview Route
   |--------------------------------------------------------------------------
   |
   | Opt in to a browser route that renders the report exactly as it would be
   | mailed. Handy while tuning the layout; keep it off in production, or guard
   | it with your own middleware. This is a genuine per-environment switch — on
   | locally, off in production — so it reads from the environment, like
   | APP_DEBUG.
   |
   */

   'preview' => [
      'enabled' => env('ERROR_AUDIT_PREVIEW', false),
      'path' => 'error-audit/preview',
      'middleware' => ['web'],
   ],

   /*
   |--------------------------------------------------------------------------
   | Queue
   |--------------------------------------------------------------------------
   |
   | Left null, generation runs on the application's default queue connection.
   | Name a connection or queue only to keep the audit off a busy queue.
   |
   */

   'queue' => [
      'connection' => null,
      'queue' => null,
      'timeout' => 1800,
   ],

   /*
   |--------------------------------------------------------------------------
   | Assessment Cache
   |--------------------------------------------------------------------------
   |
   | Assessments are kept in the framework cache, keyed by issue fingerprint,
   | so a known issue is never sent to the provider twice. The package ships no
   | schema and no migration; clearing the cache costs one re-analysis. Leave
   | null to use the application's default store.
   |
   */

   'cache' => [
      'store' => null,
   ],

];
