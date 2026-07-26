<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Notifications;

use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\ErrorAudit;
use Aaix\LaravelErrorAudit\Mail\ErrorAuditMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ErrorAuditNotification extends Notification
{
   use Queueable;

   public function __construct(public readonly AuditReport $report) {}

   /**
    * @return list<string>
    */
   public function via(object $notifiable): array
   {
      return app(ErrorAudit::class)->notificationChannels();
   }

   public function toMail(object $notifiable): ErrorAuditMail
   {
      $errorAudit = app(ErrorAudit::class);
      $mail = new ErrorAuditMail($this->report);

      $sender = $errorAudit->sender();

      if ($sender['address'] !== null) {
         $mail->from($sender['address'], $sender['name']);
      }

      $mailer = $errorAudit->value('mailer');

      if ($mailer !== null) {
         $mail->mailer($mailer);
      }

      $errorAudit->callSendingCallbacks($mail, $this->report);

      return $mail;
   }

   /**
    * Everything a custom channel needs to render the report its own way.
    *
    * @return array<string, mixed>
    */
   public function toArray(object $notifiable): array
   {
      return [
         'application' => $this->report->applicationName,
         'status' => $this->report->statusWord(),
         'period_start' => $this->report->periodStart->toIso8601String(),
         'period_end' => $this->report->periodEnd->toIso8601String(),
         'errors' => $this->report->errorCount,
         'warnings' => $this->report->warningCount,
         'issue_types' => $this->report->issueTypeCount(),
         'new_issue_types' => $this->report->newIssueTypeCount(),
         'analysis_cost_usd' => $this->report->analysisCostUsd,
         'issues' => array_map(fn ($issue): array => [
            'type' => $issue->group->title(),
            'count' => $issue->group->count(),
            'is_new' => $issue->isNew,
            'urgency' => $issue->assessment?->urgency->value,
            'category' => $issue->assessment?->category->value,
            'title' => $issue->assessment?->title,
            'likely_cause' => $issue->assessment?->likelyCause,
            'suggested_action' => $issue->assessment?->suggestedAction,
         ], $this->report->issues),
      ];
   }
}
