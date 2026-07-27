<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Enums\ContextLevelEnum;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\ErrorAudit;
use Aaix\LaravelErrorAudit\Facades\ErrorAudit as ErrorAuditFacade;
use Aaix\LaravelErrorAudit\Notifications\ErrorAuditNotification;
use Aaix\LaravelErrorAudit\Services\ErrorAuditDispatcher;
use Aaix\LaravelErrorAudit\Tests\ErrorAuditReportFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

it('prefers a runtime resolver over the config file', function (): void {
   config()->set('error-audit.ai.model', 'from-config');

   ErrorAuditFacade::modelUsing(fn (): string => 'from-settings-table');

   expect(app(ErrorAudit::class)->value('ai.model'))->toBe('from-settings-table');
});

it('falls back to config when a resolver returns null', function (): void {
   config()->set('error-audit.ai.model', 'from-config');

   ErrorAuditFacade::modelUsing(fn () => null);

   expect(app(ErrorAudit::class)->value('ai.model'))->toBe('from-config');
});

it('resolves any configuration key by name', function (): void {
   ErrorAuditFacade::resolveUsing('ai.max_issues_per_run', fn (): int => 3);

   expect(app(ErrorAudit::class)->value('ai.max_issues_per_run'))->toBe(3);
});

it('is asked again on every read, so a changed setting takes effect at once', function (): void {
   $current = 'first';

   ErrorAuditFacade::modelUsing(function () use (&$current): string {
      return $current;
   });

   expect(app(ErrorAudit::class)->value('ai.model'))->toBe('first');

   $current = 'second';

   expect(app(ErrorAudit::class)->value('ai.model'))->toBe('second');
});

it('takes recipients from a resolver', function (): void {
   ErrorAuditFacade::recipientsUsing(fn (): array => ['ops@example.com', 'lead@example.com']);

   expect(app(ErrorAudit::class)->recipients())->toBe(['ops@example.com', 'lead@example.com']);
});

it('accepts a comma separated recipient string', function (): void {
   ErrorAuditFacade::recipientsUsing(fn (): string => 'a@example.com, b@example.com');

   expect(app(ErrorAudit::class)->recipients())->toBe(['a@example.com', 'b@example.com']);
});

it('resolves the context level through the same mechanism', function (): void {
   ErrorAuditFacade::contextLevelUsing(fn (): string => 'class_only');

   expect(app(ErrorAudit::class)->contextLevel())->toBe(ContextLevelEnum::ClassOnly);
});

it('resolves the minimum level, enum or string alike', function (mixed $given): void {
   ErrorAuditFacade::minimumLevelUsing(fn () => $given);

   expect(app(ErrorAudit::class)->minimumLevel())->toBe(LogLevelEnum::Error);
})->with(['error', LogLevelEnum::Error]);

it('adds redaction patterns at runtime', function (): void {
   ErrorAuditFacade::redactUsing(fn (): array => ['/\bCUST-\d+\b/' => '{customer}']);

   $redacted = app(Aaix\LaravelErrorAudit\Redaction\LogRedactor::class)
      ->redact('order for CUST-4711 failed');

   expect($redacted->text)->toBe('order for {customer} failed');
});

it('drops filtered issues before they cost anything', function (): void {
   ErrorAuditFacade::filterIssues(
      fn (IssueGroup $group): bool => $group->exceptionClass !== 'NotFoundHttpException'
   );

   $result = app(Aaix\LaravelErrorAudit\Analysis\IssueAnalyzer::class)->analyse([
      ErrorAuditReportFactory::group('keep', 'RedisException', 2),
      ErrorAuditReportFactory::group('drop', 'NotFoundHttpException', 900),
   ], 'the last 24 hours');

   expect($result->issues)->toHaveCount(1)
      ->and($result->issues[0]->group->exceptionClass)->toBe('RedisException');
});

it('delivers the report as a notification', function (): void {
   Notification::fake();

   ErrorAuditFacade::recipientsUsing(fn (): array => ['ops@example.com']);

   app(ErrorAuditDispatcher::class)->send(ErrorAuditReportFactory::report());

   Notification::assertSentTo(
      new AnonymousNotifiable,
      ErrorAuditNotification::class,
      fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === ['ops@example.com']
         && $channels === ['mail'],
   );
});

it('routes the report to whatever notifiable the application names', function (): void {
   Notification::fake();

   $team = Notification::route('slack', 'https://hooks.example.test/abc');

   ErrorAuditFacade::notifiableUsing(fn () => $team);
   ErrorAuditFacade::channelsUsing(fn (): array => ['slack']);

   app(ErrorAuditDispatcher::class)->send(ErrorAuditReportFactory::report());

   Notification::assertSentTo(
      new AnonymousNotifiable,
      ErrorAuditNotification::class,
      fn ($notification, $channels): bool => $channels === ['slack'],
   );
});

it('hands a custom channel the full report as data', function (): void {
   $payload = (new ErrorAuditNotification(ErrorAuditReportFactory::report()))
      ->toArray(new AnonymousNotifiable);

   expect($payload['application'])->toBe('Acme IMS')
      ->and($payload['errors'])->toBe(526)
      ->and($payload['new_issue_types'])->toBe(2)
      ->and($payload['issues'][0])->toHaveKeys(['type', 'title', 'count', 'urgency', 'suggested_action']);
});

it('lets the application adjust the mail before it goes out', function (): void {
   ErrorAuditFacade::sending(function ($mail): void {
      $mail->cc('archive@example.com');
   });

   $mail = (new ErrorAuditNotification(ErrorAuditReportFactory::report()))
      ->toMail(new AnonymousNotifiable);

   expect($mail->cc)->toContain(['name' => null, 'address' => 'archive@example.com']);
});

it('applies the resolved sender to the mail', function (): void {
   ErrorAuditFacade::senderUsing(fn (): array => [
      'address' => 'audit@example.com',
      'name' => 'Nightly Audit',
   ]);

   $mail = (new ErrorAuditNotification(ErrorAuditReportFactory::report()))
      ->toMail(new AnonymousNotifiable);

   expect($mail->from)->toContain(['name' => 'Nightly Audit', 'address' => 'audit@example.com']);
});

it('puts the configured recipients on the mailable itself', function (): void {
   // MailChannel sends a returned Mailable as-is and never applies the mail
   // route, so the addresses must land on the mailable in toMail().
   $notifiable = Notification::route('mail', ['ops@example.com', 'lead@example.com']);

   $mail = (new ErrorAuditNotification(ErrorAuditReportFactory::report()))
      ->toMail($notifiable);

   expect($mail->hasTo('ops@example.com'))->toBeTrue()
      ->and($mail->hasTo('lead@example.com'))->toBeTrue();
});

it('carries a name given alongside a routed address', function (): void {
   $notifiable = Notification::route('mail', ['ops@example.com' => 'Ops Team']);

   $mail = (new ErrorAuditNotification(ErrorAuditReportFactory::report()))
      ->toMail($notifiable);

   expect($mail->hasTo('ops@example.com'))->toBeTrue()
      ->and($mail->to)->toContain(['name' => 'Ops Team', 'address' => 'ops@example.com']);
});

it('refuses to guess when nothing says where the report should go', function (): void {
   config()->set('error-audit.recipients', []);

   app(ErrorAudit::class)->notifiable();
})->throws(RuntimeException::class, 'no recipients');
