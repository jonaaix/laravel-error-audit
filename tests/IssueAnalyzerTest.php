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

it('remembers every issue it saw', function (): void {
   $result = analyse([ErrorAuditReportFactory::group('aa', 'RedisException', 4)]);

   expect($result->issues)->toHaveCount(1);

   $entry = app(AssessmentStore::class)->find('aa');

   expect($entry['last_count'])->toBe(4)
      ->and($entry['total_count'])->toBe(4)
      ->and($entry['level'])->toBe('error');
});

it('marks an issue seen for the first time as new', function (): void {
   $result = analyse([ErrorAuditReportFactory::group('bb', 'RedisException', 2)]);

   expect($result->issues[0]->isNew)->toBeTrue()
      ->and($result->issues[0]->previousCount)->toBeNull();
});

it('carries the previous count forward on the next run', function (): void {
   analyse([ErrorAuditReportFactory::group('cc', 'RedisException', 3)]);
   $result = analyse([ErrorAuditReportFactory::group('cc', 'RedisException', 9)]);

   expect($result->issues[0]->isNew)->toBeFalse()
      ->and($result->issues[0]->previousCount)->toBe(3)
      ->and($result->issues[0]->deltaPercent())->toBe(200)
      ->and(app(AssessmentStore::class)->find('cc')['total_count'])->toBe(12);
});

it('reuses a cached assessment instead of asking again', function (): void {
   seedAssessment('dd', 'RedisException', 40, UrgencyEnum::High);

   $result = analyse([ErrorAuditReportFactory::group('dd', 'RedisException', 5)]);

   expect($result->analysedCount)->toBe(0)
      ->and($result->issues[0]->assessment)->not->toBeNull()
      ->and($result->issues[0]->assessment->fromCache)->toBeTrue()
      ->and($result->issues[0]->assessment->title)->toBe('Redis is unreachable.')
      ->and($result->issues[0]->assessment->urgency)->toBe(UrgencyEnum::High);
});

it('keeps the assessment when a later run adds no new analysis', function (): void {
   seedAssessment('ee', 'RedisException', 40, UrgencyEnum::High);

   analyse([ErrorAuditReportFactory::group('ee', 'RedisException', 2)]);
   $result = analyse([ErrorAuditReportFactory::group('ee', 'RedisException', 2)]);

   expect($result->issues[0]->assessment?->title)->toBe('Redis is unreachable.');
});

it('keeps the first sighting across runs', function (): void {
   analyse([ErrorAuditReportFactory::group('ff', 'RedisException', 1)]);
   $first = app(AssessmentStore::class)->firstSeen('ff');

   analyse([ErrorAuditReportFactory::group('ff', 'RedisException', 1)]);

   expect(app(AssessmentStore::class)->firstSeen('ff')->toIso8601String())
      ->toBe($first->toIso8601String());
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

   expect($progress->issues)->toHaveCount(1)
      ->and($progress->issues[0][0])->toBe('RedisException')
      ->and($progress->issues[0][1])->toBe(5)
      ->and($progress->issues[0][2])->toBe(\Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum::Cached)
      ->and($progress->details)->toContain('AI analysis is disabled — issues are counted but not assessed');
});

it('carries the spent input token budget in its result', function (): void {
   seedAssessment('tt', 'RedisException', 6, UrgencyEnum::High);

   $result = analyse([ErrorAuditReportFactory::group('tt', 'RedisException', 6)]);

   expect($result->inputTokens)->toBe(0)
      ->and($result->maxInputTokens)->toBe(40000);
});
