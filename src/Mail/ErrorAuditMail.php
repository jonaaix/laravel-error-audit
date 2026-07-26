<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Mail;

use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\Enums\UrgencyEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErrorAuditMail extends Mailable
{
   use Queueable, SerializesModels;

   public function __construct(public readonly AuditReport $report)
   {
      $this->theme = 'error-audit::themes.audit';
   }

   public function envelope(): Envelope
   {
      return new Envelope(
         subject: $this->statusSubject(),
         metadata: [
            'error-audit-status' => $this->report->statusWord(),
         ],
      );
   }

   public function content(): Content
   {
      return new Content(
         markdown: 'error-audit::mail.report',
         text: 'error-audit::mail.report-text',
         with: ['report' => $this->report],
      );
   }

   /**
    * The subject line is the only part of the report that is read on a lock
    * screen, so it carries the status signal on its own.
    */
   private function statusSubject(): string
   {
      $symbol = match ($this->report->highestUrgency()) {
         UrgencyEnum::Critical, UrgencyEnum::High => '⚠',
         UrgencyEnum::Medium => '●',
         default => '✓',
      };

      $parts = [];

      if ($this->report->newIssueTypeCount() > 0) {
         $parts[] = __(':count new issue types', ['count' => $this->report->newIssueTypeCount()]);
      }

      $parts[] = __(':count errors', ['count' => number_format($this->report->errorCount, 0, ',', '.')]);

      return sprintf(
         '%s %s — %s, %s',
         $symbol,
         implode(' · ', $parts),
         $this->report->applicationName,
         $this->report->periodEnd->format('d.m.'),
      );
   }
}
