<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit;

use Aaix\LaravelErrorAudit\Analysis\AssessmentStore;
use Aaix\LaravelErrorAudit\Analysis\IssuePayloadBuilder;
use Aaix\LaravelErrorAudit\Analysis\SourceContext;
use Aaix\LaravelErrorAudit\Charts\GdChartRenderer;
use Aaix\LaravelErrorAudit\Console\PreviewErrorAuditCmd;
use Aaix\LaravelErrorAudit\Contracts\ChartRenderer;
use Aaix\LaravelErrorAudit\Console\SendErrorAuditCmd;
use Aaix\LaravelErrorAudit\Parsing\LaravelLogParser;
use Aaix\LaravelErrorAudit\Redaction\LogRedactor;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Aaix\LaravelErrorAudit\Http\PreviewErrorAuditController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ErrorAuditServiceProvider extends ServiceProvider
{
   public function register(): void
   {
      $this->mergeConfigFrom(__DIR__.'/../config/error-audit.php', 'error-audit');

      $this->app->singleton(ErrorAudit::class, fn ($app): ErrorAudit => new ErrorAudit(
         $app->make(ConfigRepository::class)
      ));

      $this->app->scoped(LogRedactor::class, function ($app): LogRedactor {
         $errorAudit = $app->make(ErrorAudit::class);

         return new LogRedactor(
            entropyThreshold: (int) $errorAudit->value('redaction.entropy_threshold', 24),
            discardAboveMaskedRatio: (float) $errorAudit->value('redaction.discard_above_masked_ratio', 0.5),
            extraPatterns: (array) $errorAudit->value('redaction.extra_patterns', []),
         );
      });

      $this->app->scoped(AssessmentStore::class, function ($app): AssessmentStore {
         $store = $app->make(ErrorAudit::class)->value('cache.store');

         return new AssessmentStore($app->make(CacheFactory::class)->store($store));
      });

      $this->app->scoped(ChartRenderer::class, function ($app): ChartRenderer {
         $errorAudit = $app->make(ErrorAudit::class);

         return new GdChartRenderer(
            logger: $app->make('log'),
            width: (int) $errorAudit->value('chart.width', 600),
            height: (int) $errorAudit->value('chart.height', 200),
            fontPath: $errorAudit->value('chart.font_path'),
         );
      });

      $this->app->scoped(LaravelLogParser::class, function ($app): LaravelLogParser {
         return new LaravelLogParser(
            self::intOrNull($app->make(ErrorAudit::class)->value('ai.max_stack_frames'))
         );
      });

      $this->app->scoped(IssuePayloadBuilder::class, function ($app): IssuePayloadBuilder {
         $errorAudit = $app->make(ErrorAudit::class);

         $sourceContext = null;

         if ($errorAudit->value('ai.include_source_files', false)) {
            $sourceContext = new SourceContext(
               redactor: $app->make(LogRedactor::class),
               basePath: $app->basePath(),
               maxFileBytes: (int) $errorAudit->value('ai.max_source_file_bytes', 200000),
            );
         }

         return new IssuePayloadBuilder(
            redactor: $app->make(LogRedactor::class),
            contextLevel: $errorAudit->contextLevel(),
            samplesPerIssue: (int) $errorAudit->value('ai.samples_per_issue', 2),
            maxSampleCharacters: self::intOrNull($errorAudit->value('ai.max_sample_characters')),
            maxStackFrames: self::intOrNull($errorAudit->value('ai.max_stack_frames')),
            sourceContext: $sourceContext,
            maxIssueTokens: self::intOrNull($errorAudit->value('ai.max_tokens_per_issue', 20000)),
         );
      });
   }

   private static function intOrNull(mixed $value): ?int
   {
      return $value === null ? null : (int) $value;
   }

   public function boot(): void
   {
      $this->loadViewsFrom(__DIR__.'/../resources/views', 'error-audit');
      $this->registerPreviewRoute();

      if ($this->app->runningInConsole()) {
         $this->commands([
            SendErrorAuditCmd::class,
            PreviewErrorAuditCmd::class,
         ]);

         $this->publishes([
            __DIR__.'/../config/error-audit.php' => config_path('error-audit.php'),
         ], 'error-audit-config');

         $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/error-audit'),
         ], 'error-audit-views');
      }
   }

   private function registerPreviewRoute(): void
   {
      $errorAudit = $this->app->make(ErrorAudit::class);

      if (! $errorAudit->value('preview.enabled', false)) {
         return;
      }

      Route::middleware((array) $errorAudit->value('preview.middleware', ['web']))
         ->get(
            (string) $errorAudit->value('preview.path', 'error-audit/preview'),
            PreviewErrorAuditController::class,
         )
         ->name('error-audit.preview');
   }
}
