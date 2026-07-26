<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Analysis\IssuePayloadBuilder;
use Aaix\LaravelErrorAudit\Analysis\SourceContext;
use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Enums\ContextLevelEnum;
use Aaix\LaravelErrorAudit\Enums\LogLevelEnum;
use Aaix\LaravelErrorAudit\Redaction\LogRedactor;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
   $this->base = sys_get_temp_dir().'/error-audit-budget-'.uniqid();
   mkdir($this->base.'/app/Services', 0777, true);

   file_put_contents($this->base.'/app/Services/Big.php', "<?php\n// ".str_repeat('x', 40000)."\n");
   file_put_contents($this->base.'/app/Services/Small.php', "<?php\nclass Small { public function run() {} }\n");

   $this->context = new SourceContext(new LogRedactor, $this->base);

   $this->makeGroup = function (array $frames): IssueGroup {
      $group = new IssueGroup('fp', LogLevelEnum::Error, 'App\\BigException', 'signature');
      $moment = Carbon::parse('2026-07-23 03:00:00');

      $group->add(new LogEntry(
         loggedAt: $moment,
         level: LogLevelEnum::Error,
         channel: 'daily',
         environment: 'production',
         message: 'the failure message',
         exceptionClass: 'App\\BigException',
         stackFrames: $frames,
      ), $moment->format('Y-m-d H'));

      return $group;
   };
});

afterEach(function (): void {
   exec('rm -rf '.escapeshellarg($this->base));
});

it('drops source files that would push a prompt past its token budget, and says so', function (): void {
   $builder = new IssuePayloadBuilder(
      redactor: new LogRedactor,
      contextLevel: ContextLevelEnum::Full,
      sourceContext: $this->context,
      maxIssueTokens: 200,
   );

   $payload = $builder->build(($this->makeGroup)([
      'app/Services/Big.php:2 Big->run()',
   ]), 'the last 24 hours');

   expect($payload)->toContain('the failure message')
      ->and($payload)->not->toContain(str_repeat('x', 100))
      ->and($payload)->toContain('omitted to stay within the token budget');
});

it('keeps source files when the budget is generous', function (): void {
   $builder = new IssuePayloadBuilder(
      redactor: new LogRedactor,
      contextLevel: ContextLevelEnum::Full,
      sourceContext: $this->context,
      maxIssueTokens: 20000,
   );

   $payload = $builder->build(($this->makeGroup)([
      'app/Services/Small.php:2 Small->run()',
   ]), 'the last 24 hours');

   expect($payload)->toContain('Source file app/Services/Small.php')
      ->and($payload)->toContain('class Small')
      ->and($payload)->not->toContain('omitted to stay within');
});
