<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Tests;

use Aaix\LaravelErrorAudit\Data\AuditedIssue;
use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\Data\IssueAssessment;
use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Data\TimelineBucket;
use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;
use Aaix\LaravelErrorAudit\Enums\IssueCategoryEnum;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;
use Illuminate\Support\Carbon;

class ErrorAuditReportFactory
{
   public static function group(
      string $fingerprint,
      string $exceptionClass,
      int $occurrences,
      LogLevelEnum $level = LogLevelEnum::Error,
      string $channel = 'daily',
   ): IssueGroup {
      $group = new IssueGroup($fingerprint, $level, $exceptionClass, 'signature');
      $start = Carbon::parse('2026-07-23 03:00:00');

      for ($index = 0; $index < $occurrences; $index++) {
         $moment = $start->copy()->addMinutes($index * 7);

         $group->add(new LogEntry(
            loggedAt: $moment,
            level: $level,
            channel: $channel,
            environment: 'production',
            message: $exceptionClass.' number '.$index,
            exceptionClass: $exceptionClass,
            stackFrames: ['app/Services/Import.php:'.(10 + $index).' Import->run()'],
         ), $moment->format('Y-m-d H'));
      }

      return $group;
   }

   public static function report(bool $withAssessments = true, ?string $chartPng = null): AuditReport
   {
      $definitions = [
         ['a1', 'Illuminate\Database\QueryException', 412, UrgencyEnum::Critical, IssueCategoryEnum::Bug, LogLevelEnum::Error],
         ['b2', 'RedisException', 96, UrgencyEnum::High, IssueCategoryEnum::Infrastructure, LogLevelEnum::Error],
         ['c3', 'App\Exceptions\PaymentDeclined', 18, UrgencyEnum::Medium, IssueCategoryEnum::Integration, LogLevelEnum::Error],
         ['d4', 'ErrorException', 240, UrgencyEnum::Low, IssueCategoryEnum::Deprecation, LogLevelEnum::Warning],
         ['e5', 'NotFoundHttpException', 1204, UrgencyEnum::Noise, IssueCategoryEnum::Noise, LogLevelEnum::Warning],
      ];

      $issues = [];

      foreach ($definitions as $index => [$fingerprint, $class, $count, $urgency, $category, $level]) {
         $issues[] = new AuditedIssue(
            group: self::group($fingerprint, $class, min($count, 24), $level),
            assessment: $withAssessments ? new IssueAssessment(
               $urgency,
               $category,
               'The '.class_basename($class).' surfaces while writing the nightly reconciliation batch.',
               'A migration adding the reconciliation table was never applied on this environment.',
               'Run the pending migration, then re-queue the failed batch from the horizon dashboard.',
            ) : null,
            isNew: $index < 2,
            previousCount: $index === 0 ? 298 : ($index === 3 ? 260 : null),
            outcome: $withAssessments ? AnalysisOutcomeEnum::Analysed : AnalysisOutcomeEnum::SkippedBudget,
         );
      }

      return new AuditReport(
         applicationName: 'Acme IMS',
         periodStart: Carbon::parse('2026-07-22 07:00:00'),
         periodEnd: Carbon::parse('2026-07-23 07:00:00'),
         issues: $issues,
         timeline: self::timeline(),
         channels: ['daily', 'queue'],
         errorCount: 526,
         warningCount: 1444,
         previousErrorCount: 381,
         previousWarningCount: 1502,
         analysedIssueCount: $withAssessments ? 5 : 0,
         analysisCostUsd: $withAssessments ? 0.0041 : 0.0,
         analysisModel: $withAssessments ? 'claude-haiku-4-5' : null,
         discardedEntryCount: 0,
         chartPng: $chartPng,
         analysisInputTokens: $withAssessments ? 12840 : 0,
         analysisMaxInputTokens: $withAssessments ? 40000 : 0,
      );
   }

   /**
    * @return list<TimelineBucket>
    */
   private static function timeline(): array
   {
      $shape = [
         [2, 11], [1, 8], [0, 6], [1, 9], [0, 5], [2, 7],
         [3, 12], [8, 24], [14, 31], [22, 44], [19, 38], [26, 52],
         [31, 61], [44, 77], [38, 66], [52, 92], [61, 104], [48, 88],
         [37, 71], [29, 58], [24, 49], [18, 41], [12, 33], [7, 20],
      ];

      $buckets = [];
      $cursor = Carbon::parse('2026-07-22 07:00:00');

      foreach ($shape as [$errors, $warnings]) {
         $buckets[] = new TimelineBucket(
            key: $cursor->format('Y-m-d H'),
            startsAt: $cursor->copy(),
            label: $cursor->format('H'),
            errors: $errors,
            warnings: $warnings,
         );

         $cursor->addHour();
      }

      return $buckets;
   }
}
