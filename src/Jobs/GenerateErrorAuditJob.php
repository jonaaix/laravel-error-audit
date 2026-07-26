<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Jobs;

use Aaix\LaravelErrorAudit\ErrorAudit;
use Aaix\LaravelErrorAudit\Services\ErrorAuditDispatcher;
use Aaix\LaravelErrorAudit\Services\ErrorAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class GenerateErrorAuditJob implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   public int $tries = 1;

   public function __construct(
      private readonly ?Carbon $since = null,
      private readonly ?Carbon $until = null,
      private readonly bool $refresh = false,
   ) {
      $errorAudit = app(ErrorAudit::class);

      $this->onConnection($errorAudit->value('queue.connection'));
      $this->onQueue($errorAudit->value('queue.queue'));
      $this->timeout = (int) $errorAudit->value('queue.timeout', 1800);
   }

   public function handle(ErrorAuditService $service, ErrorAuditDispatcher $dispatcher): void
   {
      $dispatcher->send($service->generate($this->since, $this->until, $this->refresh));
   }
}
