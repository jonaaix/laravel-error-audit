<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Analysis\AnalysisResult;
use Aaix\LaravelErrorAudit\Analysis\AssessmentStore;
use Aaix\LaravelErrorAudit\Analysis\IssueAnalyzer;
use Aaix\LaravelErrorAudit\Data\IssueAssessment;
use Aaix\LaravelErrorAudit\Enums\IssueCategoryEnum;
use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;
use Aaix\LaravelErrorAudit\Tests\ErrorAuditReportFactory;

function analyse(array $groups, bool $refresh = false): AnalysisResult
{
   return app(IssueAnalyzer::class)->analyse($groups, 'the last 24 hours', $refresh);
}

function seedAssessment(string $fingerprint, string $exceptionClass, int $count, UrgencyEnum $urgency): void
{
   app(AssessmentStore::class)->remember(
      ErrorAuditReportFactory::group($fingerprint, $exceptionClass, $count),
      new IssueAssessment(
         $urgency,
         IssueCategoryEnum::Infrastructure,
         'Redis is unreachable.',
         'The host name does not resolve inside the container.',
         'Check the redis service name.',
      ),
      'claude-haiku-4-5',
      0.0004,
   );
}

it('leaves nothing behind for an issue it could not assess', function (): void {
   $result = analyse([ErrorAuditReportFactory::group('aa', 'RedisException', 4)]);

   expect($result->issues)->toHaveCount(1)
      ->and(app(AssessmentStore::class)->find('aa'))->toBeNull();
});

it('marks an issue absent from the preceding window as new', function (): void {
   $result = analyse([ErrorAuditReportFactory::group('bb', 'RedisException', 2)]);

   expect($result->issues[0]->isNew)->toBeTrue()
      ->and($result->issues[0]->previousCount)->toBeNull();
});

it('takes the previous count from the preceding window', function (): void {
   $result = app(IssueAnalyzer::class)->analyse(
      [ErrorAuditReportFactory::group('cc', 'RedisException', 9)],
      'the last 24 hours',
      previousGroups: ['cc' => ErrorAuditReportFactory::group('cc', 'RedisException', 3)],
   );

   expect($result->issues[0]->isNew)->toBeFalse()
      ->and($result->issues[0]->previousCount)->toBe(3)
      ->and($result->issues[0]->deltaPercent())->toBe(200);
});

it('produces the identical result when run twice over the same window', function (): void {
   $run = fn () => app(IssueAnalyzer::class)->analyse(
      [ErrorAuditReportFactory::group('dd2', 'RedisException', 4)],
      'the last 24 hours',
   );

   $first = $run();
   $second = $run();

   expect($second->issues[0]->isNew)->toBe($first->issues[0]->isNew)
      ->and($second->issues[0]->previousCount)->toBe($first->issues[0]->previousCount)
      ->and($second->issues[0]->assessment?->title)->toBe($first->issues[0]->assessment?->title);
});

it('reuses a cached assessment instead of asking again', function (): void {
   seedAssessment('dd', 'RedisException', 40, UrgencyEnum::High);

   $result = analyse([ErrorAuditReportFactory::group('dd', 'RedisException', 5)]);

   expect($result->analysedCount)->toBe(1)
      ->and($result->issues[0]->assessment)->not->toBeNull()
      ->and($result->issues[0]->assessment->fromCache)->toBeTrue()
      ->and($result->issues[0]->assessment->title)->toBe('Redis is unreachable.')
      ->and($result->issues[0]->assessment->urgency)->toBe(UrgencyEnum::High);
});

it('counts cached assessments as analysed and carries their true cost', function (): void {
   seedAssessment('cc1', 'RedisException', 40, UrgencyEnum::High);
   seedAssessment('cc2', 'QueryException', 12, UrgencyEnum::Medium);

   $result = analyse([
      ErrorAuditReportFactory::group('cc1', 'RedisException', 5),
      ErrorAuditReportFactory::group('cc2', 'QueryException', 3),
      ErrorAuditReportFactory::group('cc3', 'PaymentDeclined', 1),
   ]);

   // Two of three carry an assessment (from cache), the third stays open —
   // and the report's cost is the sum of what those assessments once cost.
   expect($result->analysedCount)->toBe(2)
      ->and($result->costUsd)->toBe(0.0008)
      ->and($result->model)->toBe('claude-haiku-4-5');
});

it('keeps the assessment when a later run adds no new analysis', function (): void {
   seedAssessment('ee', 'RedisException', 40, UrgencyEnum::High);

   analyse([ErrorAuditReportFactory::group('ee', 'RedisException', 2)]);
   $result = analyse([ErrorAuditReportFactory::group('ee', 'RedisException', 2)]);

   expect($result->issues[0]->assessment?->title)->toBe('Redis is unreachable.');
});

it('sorts the most urgent issue to the top regardless of frequency', function (): void {
   seedAssessment('rare', 'PaymentFailed', 1, UrgencyEnum::Critical);
   seedAssessment('common', 'NotFoundHttpException', 900, UrgencyEnum::Noise);

   $result = analyse([
      ErrorAuditReportFactory::group('common', 'NotFoundHttpException', 20),
      ErrorAuditReportFactory::group('rare', 'PaymentFailed', 1),
   ]);

   expect($result->issues[0]->group->fingerprint)->toBe('rare');
});

it('makes no request while the analysis is switched off', function (): void {
   config()->set('error-audit.ai.enabled', false);

   $result = analyse([ErrorAuditReportFactory::group('ii', 'RedisException', 3)]);

   expect($result->analysedCount)->toBe(0)
      ->and($result->issues[0]->assessment)->toBeNull()
      ->and($result->costUsd)->toBe(0.0);
});

it('ignores a cached assessment when a refresh is forced', function (): void {
   seedAssessment('jj', 'RedisException', 40, UrgencyEnum::High);

   $result = analyse([ErrorAuditReportFactory::group('jj', 'RedisException', 5)], refresh: true);

   expect($result->issues[0]->assessment)->toBeNull();
});

it('reports per-issue progress while analysing', function (): void {
   $progress = new class implements \Aaix\LaravelErrorAudit\Contracts\AuditProgress
   {
      public array $issues = [];

      public array $details = [];

      public function phase(string $description): void {}

      public function detail(string $description): void
      {
         $this->details[] = $description;
      }

      public function issue(string $title, int $occurrences, \Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum $outcome, ?float $costUsd = null): void
      {
         $this->issues[] = [$title, $occurrences, $outcome];
      }
   };

   seedAssessment('pp', 'RedisException', 12, UrgencyEnum::High);

   app(IssueAnalyzer::class)->analyse(
      [
         ErrorAuditReportFactory::group('pp', 'RedisException', 5),
         ErrorAuditReportFactory::group('qq', 'QueryException', 3),
      ],
      'the last 24 hours',
      progress: $progress,
   );

   expect($progress->issues)->toHaveCount(2)
      ->and($progress->issues[0][0])->toBe('RedisException')
      ->and($progress->issues[0][1])->toBe(5)
      ->and($progress->issues[0][2])->toBe(\Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum::Cached)
      ->and($progress->issues[1][0])->toBe('QueryException')
      ->and($progress->issues[1][2])->toBe(\Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum::Disabled)
      ->and($progress->details)->toContain('AI analysis is disabled — issues are counted but not assessed');
});

it('carries the spent input token budget in its result', function (): void {
   config()->set('error-audit.ai.max_input_tokens', 40000);
   seedAssessment('tt', 'RedisException', 6, UrgencyEnum::High);

   $result = analyse([ErrorAuditReportFactory::group('tt', 'RedisException', 6)]);

   expect($result->inputTokens)->toBe(0)
      ->and($result->maxInputTokens)->toBe(40000);
});

it('reports no token ceiling under the default money budget', function (): void {
   seedAssessment('uu', 'RedisException', 6, UrgencyEnum::High);

   $result = analyse([ErrorAuditReportFactory::group('uu', 'RedisException', 6)]);

   expect($result->maxInputTokens)->toBe(0);
});
