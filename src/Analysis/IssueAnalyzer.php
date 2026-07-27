<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Agents\ErrorAuditAgent;
use Aaix\LaravelErrorAudit\Contracts\AuditProgress;
use Aaix\LaravelErrorAudit\Data\AuditedIssue;
use Aaix\LaravelErrorAudit\Enums\AnalysisOutcomeEnum;
use Aaix\LaravelErrorAudit\Support\NullProgress;
use Aaix\LaravelErrorAudit\Data\IssueAssessment;
use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\ErrorAudit;
use Psr\Log\LoggerInterface;
use Throwable;

class IssueAnalyzer
{
   public function __construct(
      private readonly IssuePayloadBuilder $payloadBuilder,
      private readonly AssessmentStore $store,
      private readonly ErrorAudit $errorAudit,
      private readonly LoggerInterface $logger,
   ) {}

   /**
    * The report is a pure function of the analysed window: "new" and the
    * previous count come from comparing against the preceding window of the
    * same length, never from state left behind by earlier runs. Only the AI
    * assessment cache persists across runs — it saves requests, it does not
    * change what the report says happened.
    *
    * @param  list<IssueGroup>  $groups  Ordered by frequency, most frequent first.
    * @param  array<string, IssueGroup>  $previousGroups  Issues of the preceding window, keyed by fingerprint.
    */
   public function analyse(
      array $groups,
      string $periodDescription,
      bool $refresh = false,
      ?AuditProgress $progress = null,
      array $previousGroups = [],
   ): AnalysisResult {
      $progress ??= new NullProgress;

      $budget = new AnalysisBudget(
         maxIssues: (int) $this->errorAudit->value('ai.max_issues_per_run', 100),
         maxInputTokens: (int) $this->errorAudit->value('ai.max_input_tokens', 40000),
      );

      $groups = $this->errorAudit->applyIssueFilters($groups);

      if ($groups === []) {
         $progress->detail('nothing to analyse');
      } elseif (! $this->aiEnabled()) {
         $progress->detail('AI analysis is disabled — issues are counted but not assessed');
      }

      $agent = new ErrorAuditAgent;
      $analysedCount = 0;
      $issues = [];

      foreach ($groups as $group) {
         $assessment = $refresh ? null : $this->store->assessmentFor($group->fingerprint);

         if ($assessment !== null) {
            $progress->issue($group->title(), $group->count(), AnalysisOutcomeEnum::Cached);
         } elseif ($this->aiEnabled()) {
            $payload = $this->payloadBuilder->build($group, $periodDescription);

            if ($budget->allows($this->payloadBuilder->estimateTokens($payload))) {
               $assessment = $this->assess($agent, $payload);

               if ($assessment !== null) {
                  $analysedCount++;
                  $budget->consume($this->payloadBuilder->estimateTokens($payload));
                  $this->store->remember($group, $assessment, $agent->lastCost()?->model, $agent->lastCost()?->totalCostUsd);

                  $progress->issue(
                     $group->title(),
                     $group->count(),
                     AnalysisOutcomeEnum::Analysed,
                     $agent->lastCost()?->totalCostUsd,
                  );
               } else {
                  $progress->issue($group->title(), $group->count(), AnalysisOutcomeEnum::Failed);
               }
            } else {
               $progress->issue($group->title(), $group->count(), AnalysisOutcomeEnum::SkippedBudget);
            }
         }

         $previous = $previousGroups[$group->fingerprint] ?? null;

         $issues[] = new AuditedIssue(
            group: $group,
            assessment: $assessment,
            isNew: $previous === null,
            previousCount: $previous?->count(),
         );
      }

      $issues = $this->sortByUrgency($issues);

      return new AnalysisResult(
         issues: $issues,
         analysedCount: $analysedCount,
         costUsd: $agent->totalCostUsd(),
         model: $agent->lastCost()?->model,
         inputTokens: $budget->tokensSpent(),
         maxInputTokens: $budget->maxInputTokens(),
      );
   }

   private function assess(ErrorAuditAgent $agent, string $payload): ?IssueAssessment
   {
      try {
         $response = $agent->prompt(
            $payload,
            provider: $this->errorAudit->value('ai.provider'),
            model: $this->errorAudit->value('ai.model'),
            timeout: (int) $this->errorAudit->value('ai.timeout', 120),
         );

         $decoded = json_decode($response->text, true, flags: JSON_THROW_ON_ERROR);

         return is_array($decoded) ? IssueAssessment::fromArray($decoded) : null;
      } catch (Throwable $exception) {
         $this->logger->warning('Error audit: issue analysis failed.', [
            'exception' => $exception->getMessage(),
         ]);

         return null;
      }
   }

   /**
    * @param  list<AuditedIssue>  $issues
    * @return list<AuditedIssue>
    */
   private function sortByUrgency(array $issues): array
   {
      usort($issues, function (AuditedIssue $a, AuditedIssue $b): int {
         $rankA = $a->assessment?->urgency->rank() ?? 2;
         $rankB = $b->assessment?->urgency->rank() ?? 2;

         return $rankA <=> $rankB ?: $b->group->count() <=> $a->group->count();
      });

      return $issues;
   }

   private function aiEnabled(): bool
   {
      return $this->errorAudit->aiEnabled();
   }
}
