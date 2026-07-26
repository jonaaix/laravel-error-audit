<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Analysis\SourceContext;
use Aaix\LaravelErrorAudit\Redaction\LogRedactor;

beforeEach(function (): void {
   $this->base = sys_get_temp_dir().'/error-audit-src-'.uniqid();
   mkdir($this->base.'/app/Services', 0777, true);
   mkdir($this->base.'/vendor/acme', 0777, true);

   file_put_contents($this->base.'/app/Services/Import.php', "<?php\nclass Import { public function run() {} }\n");
   file_put_contents($this->base.'/vendor/acme/Lib.php', "<?php\nclass Lib {}\n");

   $this->context = new SourceContext(new LogRedactor, $this->base);
});

afterEach(function (): void {
   exec('rm -rf '.escapeshellarg($this->base));
});

it('loads an application source file named in the trace', function (): void {
   $sources = $this->context->forFrames([
      'app/Services/Import.php:5 Import->run()',
      'vendor/laravel/framework/src/Foo.php:10 Foo->bar()',
   ]);

   expect($sources)->toHaveKey('app/Services/Import.php')
      ->and($sources['app/Services/Import.php'])->toContain('class Import');
});

it('never reads a vendor file', function (): void {
   $sources = $this->context->forFrames([
      'vendor/acme/Lib.php:2 Lib->x()',
   ]);

   expect($sources)->toBeEmpty();
});

it('loads every distinct application file in the trace', function (): void {
   mkdir($this->base.'/app/Console', 0777, true);
   file_put_contents($this->base.'/app/Console/Cmd.php', "<?php\nclass Cmd {}\n");

   $sources = $this->context->forFrames([
      'app/Console/Cmd.php:3 Cmd->handle()',
      'app/Services/Import.php:5 Import->run()',
      'app/Services/Import.php:9 Import->flush()',
   ]);

   expect($sources)->toHaveCount(2)
      ->and($sources)->toHaveKeys(['app/Console/Cmd.php', 'app/Services/Import.php']);
});

it('ignores a frame that resolves outside the project root', function (): void {
   $sources = $this->context->forFrames([
      'app/../../../etc/passwd:1 x()',
   ]);

   expect($sources)->toBeEmpty();
});

it('skips a file larger than the byte ceiling', function (): void {
   $context = new SourceContext(new LogRedactor, $this->base, maxFileBytes: 10);

   $sources = $context->forFrames(['app/Services/Import.php:5 Import->run()']);

   expect($sources)->toBeEmpty();
});

it('redacts secrets inside the source before returning it', function (): void {
   file_put_contents(
      $this->base.'/app/Services/Import.php',
      "<?php\n\$key = 'sk-abcdef1234567890abcdefghij';\n"
   );

   $sources = $this->context->forFrames(['app/Services/Import.php:2 Import->run()']);

   expect($sources['app/Services/Import.php'])->not->toContain('sk-abcdef1234567890abcdefghij');
});
