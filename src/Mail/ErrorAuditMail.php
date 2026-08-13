<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Mail;

use Aaix\LaravelErrorAudit\Data\AuditReport;
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
    * screen: the "Audit" keyword up front so the mail is spotted in a stacked
    * inbox, then the date so same-day reports sort at a glance, then the two
    * counts that matter, then which application is talking.
    */
   private function statusSubject(): string
   {
      return sprintf(
         '%s %s — %s · %s — %s',
         __('Audit'),
         $this->report->periodEnd->format('d.m.'),
         __(':count ERRORS', ['count' => number_format($this->report->errorCount, 0, ',', '.')]),
         __(':count WARNINGS', ['count' => number_format($this->report->warningCount, 0, ',', '.')]),
         $this->report->applicationName,
      );
   }
}
