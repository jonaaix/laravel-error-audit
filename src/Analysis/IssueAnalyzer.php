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
      private readonly DailyCostLedger $ledger,
      private readonly ErrorAudit $errorAudit,
      private readonly LoggerInterface $logger,
   ) {}

   /**
    * The report is a pure function of the analysed window. Only the AI
    * assessment cache persists across runs — it saves requests, it does not
    * change what the report says happened.
    *
    * @param  list<IssueGroup>  $groups  Ordered by frequency, most frequent first.
    */
   public function analyse(
      array $groups,
      string $periodDescription,
      bool $refresh = false,
      ?AuditProgress $progress = null,
   ): AnalysisResult {
      $progress ??= new NullProgress;

      $maxIssues = $this->errorAudit->value('ai.max_issues_per_run');
      $maxTokens = $this->errorAudit->value('ai.max_input_tokens');
      $maxDailyCost = $this->errorAudit->value('ai.max_daily_cost_usd', 1.00);

      $budget = new AnalysisBudget(
         maxIssues: $maxIssues === null ? null : (int) $maxIssues,
         maxInputTokens: $maxTokens === null ? null : (int) $maxTokens,
      );

      $maxDailyCost = $maxDailyCost === null ? null : (float) $maxDailyCost;

      $groups = $this->errorAudit->applyIssueFilters($groups);

      if ($groups === []) {
         $progress->detail('nothing to analyse');
      } elseif (! $this->aiEnabled()) {
         $progress->detail('AI analysis is disabled — issues are counted but not assessed');
      }

      $agent = new ErrorAuditAgent;
      $analysedCount = 0;
      $cachedCostUsd = 0.0;
      $cachedModel = null;
      $issues = [];

      foreach ($groups as $group) {
         $assessment = $refresh ? null : $this->store->assessmentFor($group->fingerprint);
         $cost = null;

         if ($assessment !== null) {
            // A cached assessment is an analysed issue all the same — the
            // request was simply paid on an earlier run. Count it, and carry
            // its original cost so the report states what it truly cost.
            $analysedCount++;
            $entry = $this->store->find($group->fingerprint);
            $cachedCostUsd += (float) ($entry['cost_usd'] ?? 0);
            $cachedModel ??= $entry['model'] ?? null;

            $outcome = AnalysisOutcomeEnum::Cached;
         } elseif (! $this->aiEnabled()) {
            $outcome = AnalysisOutcomeEnum::Disabled;
         } elseif ($maxDailyCost !== null && $this->ledger->spentToday() >= $maxDailyCost) {
            $outcome = AnalysisOutcomeEnum::SkippedCost;
         } else {
            $payload = $this->payloadBuilder->build($group, $periodDescription);

            if (! $budget->allows($this->payloadBuilder->estimateTokens($payload))) {
               $outcome = AnalysisOutcomeEnum::SkippedBudget;
            } else {
               $assessment = $this->assess($agent, $payload);
               $cost = $agent->lastCost()?->totalCostUsd;
               $this->ledger->add((float) ($cost ?? 0));

               if ($assessment !== null) {
                  $analysedCount++;
                  $budget->consume($this->payloadBuilder->estimateTokens($payload));
                  $this->store->remember($group, $assessment, $agent->lastCost()?->model, $cost);

                  $outcome = AnalysisOutcomeEnum::Analysed;
               } else {
                  $outcome = AnalysisOutcomeEnum::Failed;
               }
            }
         }

         $progress->issue($group->title(), $group->count(), $outcome, $cost);

         $issues[] = new AuditedIssue(
            group: $group,
            assessment: $assessment,
            outcome: $outcome,
         );
      }

      $issues = $this->sortByUrgency($issues);

      if ($maxDailyCost !== null) {
         $progress->detail(sprintf(
            'daily analysis spend: $%.4f of $%.2f',
            $this->ledger->spentToday(),
            $maxDailyCost,
         ));
      }

      return new AnalysisResult(
         issues: $issues,
         analysedCount: $analysedCount,
         costUsd: $agent->totalCostUsd() + $cachedCostUsd,
         model: $agent->lastCost()?->model ?? $cachedModel,
         inputTokens: $budget->tokensSpent(),
         maxInputTokens: $budget->maxInputTokens() ?? 0,
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
