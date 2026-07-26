<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Redaction;

class LogRedactor
{
   private const SENSITIVE_KEYS = 'password|passwd|pwd|secret|client_secret|api_key|apikey|access_token|refresh_token|auth_token|token|authorization|private_key|session|cookie|csrf|_token|signature';

   /**
    * Applied in order; the narrow patterns must run before the broad ones or a
    * generic rule swallows the structure a specific rule relies on.
    *
    * @var array<string, string>
    */
   private const PATTERNS = [
      '/-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----/s' => '{private-key}',
      '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s' => '{certificate}',
      '/\bAWS4-HMAC-SHA256\s+Credential=[^\s,]+/i' => 'AWS4-HMAC-SHA256 Credential={redacted}',
      '/\b(?:Authorization|Proxy-Authorization)\s*[:=]\s*[^\r\n;,"\']+/i' => 'Authorization: {redacted}',
      '/\b(?:Bearer|Basic|Digest)\s+[A-Za-z0-9._\-\/+=]{8,}/i' => 'Bearer {redacted}',
      '/\beyJ[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]+/' => '{jwt}',
      '/\bAKIA[0-9A-Z]{16}\b/' => '{aws-access-key}',
      '/\bASIA[0-9A-Z]{16}\b/' => '{aws-session-key}',
      '/\bAIza[0-9A-Za-z_\-]{35}\b/' => '{google-api-key}',
      '/\bya29\.[0-9A-Za-z_\-]+/' => '{google-oauth-token}',
      '/\b(?:AccountKey|SharedAccessSignature|SharedAccessKey)=[^;\s"\']+/i' => '{azure-key}',
      '/\b(?:Set-Cookie|Cookie)\s*:\s*[^\r\n]+/i' => 'Cookie: {redacted}',
      '/\b(?:laravel_session|XSRF-TOKEN|PHPSESSID|remember_web_[A-Za-z0-9]+)=[^\s;&"\']+/i' => '{session}',
      '/\b([a-z][a-z0-9+.\-]*):\/\/[^\s:@\/]+:[^\s@\/]+@/i' => '$1://{credentials}@',
      '/"(?:'.self::SENSITIVE_KEYS.')"\s*:\s*(?:"[^"]*"|null|\d+|true|false)/i' => '"{sensitive}":"{redacted}"',
      '/[\'"]?\b(?:'.self::SENSITIVE_KEYS.')\b[\'"]?\s*(?:=>|=|:)\s*[\'"]?[^\s,&\'"\)\]\}\{]+/i' => '{sensitive}={redacted}',
      '/\b(?:\d{4}[ \-]?){3}\d{1,4}\b/' => '{card-number}',
      '/\b[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]{4}){2,7}[ ]?[A-Z0-9]{1,4}\b/' => '{iban}',
      '/\b[\w.+\-]+@[\w\-]+\.[\w.\-]{2,}\b/' => '{email}',
   ];

   public function __construct(
      private readonly int $entropyThreshold = 24,
      private readonly float $discardAboveMaskedRatio = 0.5,
      private readonly array $extraPatterns = [],
   ) {}

   public function redact(string $text): RedactionResult
   {
      if ($text === '') {
         return new RedactionResult('', 0.0, false);
      }

      $originalLength = mb_strlen($text);
      $maskedCharacters = 0;
      $redacted = $text;

      foreach ([...self::PATTERNS, ...$this->extraPatterns] as $pattern => $replacement) {
         $redacted = preg_replace_callback(
            $pattern,
            function (array $matches) use ($replacement, &$maskedCharacters): string {
               $maskedCharacters += mb_strlen($matches[0]);

               return preg_replace_callback(
                  '/\$(\d)/',
                  fn (array $reference): string => $matches[(int) $reference[1]] ?? '',
                  $replacement,
               ) ?? $replacement;
            },
            $redacted,
         ) ?? $redacted;
      }

      $redacted = $this->maskHighEntropyTokens($redacted, $maskedCharacters);

      $ratio = $originalLength > 0 ? min(1.0, $maskedCharacters / $originalLength) : 0.0;

      return new RedactionResult(
         $redacted,
         $ratio,
         $ratio > $this->discardAboveMaskedRatio,
      );
   }

   /**
    * Catches credential formats no pattern list anticipates. A token qualifies
    * when it is long enough and its Shannon entropy exceeds 3.5 bits per
    * character — the point above which a string stops resembling prose or an
    * identifier and starts resembling a generated secret.
    */
   private function maskHighEntropyTokens(string $text, int &$maskedCharacters): string
   {
      $pattern = '/\b[A-Za-z0-9+\/_\-]{'.$this->entropyThreshold.',}={0,2}/';

      return preg_replace_callback(
         $pattern,
         function (array $matches) use (&$maskedCharacters): string {
            $token = $matches[0];

            if (! $this->looksGenerated($token)) {
               return $token;
            }

            $maskedCharacters += mb_strlen($token);

            return '{redacted}';
         },
         $text,
      ) ?? $text;
   }

   private function looksGenerated(string $token): bool
   {
      $hasDigit = preg_match('/\d/', $token) === 1;
      $hasLetter = preg_match('/[A-Za-z]/', $token) === 1;

      if (! $hasDigit || ! $hasLetter) {
         return false;
      }

      return $this->shannonEntropy($token) >= 3.5;
   }

   private function shannonEntropy(string $token): float
   {
      $length = strlen($token);

      if ($length === 0) {
         return 0.0;
      }

      $entropy = 0.0;

      foreach (count_chars($token, 1) as $occurrences) {
         $probability = $occurrences / $length;
         $entropy -= $probability * log($probability, 2);
      }

      return $entropy;
   }
}
