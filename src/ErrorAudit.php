<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit;

use Aaix\LaravelErrorAudit\Data\AuditReport;
use Aaix\LaravelErrorAudit\Enums\ContextLevelEnum;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Mail\ErrorAuditMail;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Every setting this package reads goes through here.
 *
 * Config files are static and, once cached, frozen at deploy time. An
 * application that keeps its recipients in a settings table or lets an
 * administrator pick the model needs to answer at runtime instead, so each key
 * accepts a closure that is asked first and falls back to config when it
 * returns null.
 */
class ErrorAudit
{
   /** @var array<string, Closure> */
   private array $resolvers = [];

   /** @var list<Closure> */
   private array $sendingCallbacks = [];

   /** @var list<Closure> */
   private array $issueFilters = [];

   public function __construct(private readonly ConfigRepository $config) {}

   /**
    * Resolve any configuration key at runtime, addressed exactly as it appears
    * in the config file: "ai.model", "redaction.context_level", "channels.exclude".
    */
   public function resolveUsing(string $key, Closure $resolver): static
   {
      $this->resolvers[$key] = $resolver;

      return $this;
   }

   public function recipientsUsing(Closure $resolver): static
   {
      return $this->resolveUsing('recipients', $resolver);
   }

   public function senderUsing(Closure $resolver): static
   {
      return $this->resolveUsing('from', $resolver);
   }

   public function mailerUsing(Closure $resolver): static
   {
      return $this->resolveUsing('mailer', $resolver);
   }

   /**
    * Decide who receives the report: a user, a team, a collection of models,
    * anything notifiable. Overrides the configured recipient addresses.
    */
   public function notifiableUsing(Closure $resolver): static
   {
      return $this->resolveUsing('notifiable', $resolver);
   }

   /**
    * Choose the notification channels the report goes out on — mail, slack,
    * a custom channel of your own, or several at once.
    */
   public function channelsUsing(Closure $resolver): static
   {
      return $this->resolveUsing('channels_for_notification', $resolver);
   }

   public function providerUsing(Closure $resolver): static
   {
      return $this->resolveUsing('ai.provider', $resolver);
   }

   public function modelUsing(Closure $resolver): static
   {
      return $this->resolveUsing('ai.model', $resolver);
   }

   public function periodUsing(Closure $resolver): static
   {
      return $this->resolveUsing('period', $resolver);
   }

   public function minimumLevelUsing(Closure $resolver): static
   {
      return $this->resolveUsing('minimum_level', $resolver);
   }

   public function contextLevelUsing(Closure $resolver): static
   {
      return $this->resolveUsing('redaction.context_level', $resolver);
   }

   /**
    * Register extra redaction patterns, as pattern => replacement.
    */
   public function redactUsing(Closure $resolver): static
   {
      return $this->resolveUsing('redaction.extra_patterns', $resolver);
   }

   /**
    * Drop issues before they reach the analysis, and therefore before they cost
    * anything. Return false to discard the issue.
    */
   public function filterIssues(Closure $filter): static
   {
      $this->issueFilters[] = $filter;

      return $this;
   }

   /**
    * Inspect or adjust the mailable just before delivery — add a copy
    * recipient, rewrite the subject, attach something of your own.
    */
   public function sending(Closure $callback): static
   {
      $this->sendingCallbacks[] = $callback;

      return $this;
   }

   public function value(string $key, mixed $default = null): mixed
   {
      if (isset($this->resolvers[$key])) {
         $resolved = ($this->resolvers[$key])();

         if ($resolved !== null) {
            return $resolved;
         }
      }

      return $this->config->get('error-audit.'.$key, $default);
   }

   /**
    * @return list<string>
    */
   public function recipients(): array
   {
      $recipients = $this->value('recipients', []);

      if (is_string($recipients)) {
         $recipients = explode(',', $recipients);
      }

      return array_values(array_filter(array_map('trim', (array) $recipients)));
   }

   /**
    * @return array{address: ?string, name: ?string}
    */
   public function sender(): array
   {
      $from = (array) $this->value('from', []);

      return [
         'address' => $from['address'] ?? null,
         'name' => $from['name'] ?? null,
      ];
   }

   /**
    * The notifiable the report is delivered to. Without a resolver the
    * configured addresses are wrapped in an on demand mail route.
    */
   public function notifiable(): mixed
   {
      $notifiable = $this->value('notifiable');

      if ($notifiable !== null) {
         return $notifiable;
      }

      $recipients = $this->recipients();

      if ($recipients === []) {
         throw new RuntimeException(
            'Error audit has no recipients. Set error-audit.recipients, or register '
            .'ErrorAudit::notifiableUsing() to route the report yourself.'
         );
      }

      return Notification::route('mail', $recipients);
   }

   /**
    * @return list<string>
    */
   public function notificationChannels(): array
   {
      return array_values((array) $this->value('channels_for_notification', ['mail']));
   }

   public function contextLevel(): ContextLevelEnum
   {
      return ContextLevelEnum::fromMixed($this->value('redaction.context_level'));
   }

   public function minimumLevel(): LogLevelEnum
   {
      $level = $this->value('minimum_level', LogLevelEnum::Warning->value);

      return $level instanceof LogLevelEnum
         ? $level
         : (LogLevelEnum::tryFromLabel((string) $level) ?? LogLevelEnum::Warning);
   }

   public function aiEnabled(): bool
   {
      return (bool) $this->value('ai.enabled', true);
   }

   /**
    * @param  list<\Aaix\LaravelErrorAudit\Data\IssueGroup>  $groups
    * @return list<\Aaix\LaravelErrorAudit\Data\IssueGroup>
    */
   public function applyIssueFilters(array $groups): array
   {
      foreach ($this->issueFilters as $filter) {
         $groups = array_values(array_filter($groups, fn ($group): bool => $filter($group) !== false));
      }

      return $groups;
   }

   public function callSendingCallbacks(ErrorAuditMail $mail, AuditReport $report): void
   {
      foreach ($this->sendingCallbacks as $callback) {
         $callback($mail, $report);
      }
   }
}
