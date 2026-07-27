<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Services;

use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\ErrorAudit;
use Aaix\LaravelErrorAudit\Notifications\ErrorAuditNotification;
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;

/**
 * Delivery goes through Laravel's notification layer, so the application keeps
 * control over who is notified and on which channels — a user model, a team, a
 * Slack webhook, or a channel of its own.
 */
class ErrorAuditDispatcher
{
   public function __construct(
      private readonly NotificationDispatcher $notifications,
      private readonly ErrorAudit $errorAudit,
   ) {}

   /**
    * Nothing to report means no mail, unless the application opts into an
    * "all clear" heartbeat via send_empty_reports.
    *
    * @return bool Whether a report was actually sent.
    */
   public function send(AuditReport $report): bool
   {
      if ($report->isEmpty() && ! (bool) $this->errorAudit->value('send_empty_reports', false)) {
         return false;
      }

      $this->notifications->send(
         $this->errorAudit->notifiable(),
         new ErrorAuditNotification($report),
      );

      return true;
   }

   /**
    * @return list<string>
    */
   public function describeRecipients(): array
   {
      return $this->errorAudit->recipients();
   }
}
