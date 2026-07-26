<?php

declare(strict_types=1);

use Aaix\LaravelErrorAudit\Redaction\LogRedactor;

function redact(string $text): string
{
   return (new LogRedactor)->redact($text)->text;
}

it('masks credentials and identifiers before they can leave the application', function (string $input, string $leak): void {
   expect(redact($input))->not->toContain($leak);
})->with([
   'email' => ['User jonas@example.com failed to log in', 'jonas@example.com'],
   'bearer token' => ['Authorization: Bearer abcdef1234567890abcdef', 'abcdef1234567890abcdef'],
   'basic auth' => ['Authorization: Basic am9uYXM6aHVudGVyMg==', 'am9uYXM6aHVudGVyMg=='],
   'aws signature' => ['AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20260723', 'AKIAIOSFODNN7EXAMPLE'],
   'aws access key' => ['key AKIAIOSFODNN7EXAMPLE used', 'AKIAIOSFODNN7EXAMPLE'],
   'google api key' => ['key AIzaSyD-1234567890abcdefghijklmnopqrstuv rejected', 'AIzaSyD-1234567890abcdefghijklmnopqrstuv'],
   'google oauth token' => ['token ya29.a0AfH6SMBx7Yq rejected', 'ya29.a0AfH6SMBx7Yq'],
   'azure key' => ['AccountKey=abc123def456ghi789;Endpoint=x', 'abc123def456ghi789'],
   'jwt' => ['token eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NSJ9.dBjftJeZ4CVP', 'eyJhbGciOiJIUzI1NiJ9'],
   'json password' => ['payload {"password":"hunter2","name":"jonas"}', 'hunter2'],
   'array password' => ["['password' => 'hunter2']", 'hunter2'],
   'query api key' => ['GET /v1/things?api_key=sk-abc123def456 failed', 'sk-abc123def456'],
   'database dsn' => ['mysql://root:supersecret@db:3306/app unreachable', 'supersecret'],
   'cookie header' => ['Cookie: laravel_session=abc123; other=1', 'abc123'],
   'session cookie' => ['laravel_session=eyJpdiI6InNvbWV0aGluZyJ9 expired', 'eyJpdiI6InNvbWV0aGluZyJ9'],
   'credit card' => ['card 4111 1111 1111 1111 declined', '4111 1111 1111 1111'],
   'iban' => ['transfer to DE89370400440532013000 failed', 'DE89370400440532013000'],
   'private key' => ["-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA\n-----END RSA PRIVATE KEY-----", 'MIIEowIBAAKCAQEA'],
]);

it('masks generated looking tokens no pattern anticipates', function (): void {
   $redacted = redact('callback returned k7Fq2xZ9pL4vB8nR3wT6yH1c');

   expect($redacted)->toContain('{redacted}')
      ->and($redacted)->not->toContain('k7Fq2xZ9pL4vB8nR3wT6yH1c');
});

it('leaves ordinary prose and class names intact', function (string $text): void {
   expect(redact($text))->toBe($text);
})->with([
   'Illuminate\Database\QueryException was thrown',
   'Base table or view not found in the reporting schema',
   'The queue worker stopped after a graceful restart',
]);

it('discards a line that is mostly masked', function (): void {
   $result = (new LogRedactor)->redact('jonas@example.com peter@example.com anna@example.com x');

   expect($result->shouldDiscard)->toBeTrue();
});

it('keeps a line where only a small part had to be masked', function (): void {
   $result = (new LogRedactor)->redact(
      'The scheduled import for the nightly reconciliation run failed while contacting jonas@example.com about it.'
   );

   expect($result->shouldDiscard)->toBeFalse()
      ->and($result->text)->toContain('{email}');
});

it('accepts additional patterns from configuration', function (): void {
   $redactor = new LogRedactor(extraPatterns: ['/\bCUST-\d+\b/' => '{customer}']);

   expect($redactor->redact('order for CUST-4711 failed')->text)->toBe('order for {customer} failed');
});
