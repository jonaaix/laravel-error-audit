<?php

declare(strict_types=1);

namespace Aaix\LaravelErrorAudit\Analysis;

use Aaix\LaravelErrorAudit\Data\IssueGroup;
use Aaix\LaravelErrorAudit\Data\LogEntry;
use Aaix\LaravelErrorAudit\Enums\ContextLevelEnum;
use Aaix\LaravelErrorAudit\Redaction\LogRedactor;

class IssuePayloadBuilder
{
   public function __construct(
      private readonly LogRedactor $redactor,
      private readonly ContextLevelEnum $contextLevel,
      private readonly int $samplesPerIssue = 1,
      private readonly ?int $maxSampleCharacters = null,
      private readonly ?int $maxStackFrames = null,
      private readonly ?SourceContext $sourceContext = null,
      private readonly ?int $maxIssueTokens = null,
   ) {}

   /**
    * Everything the provider ever sees about an issue is assembled here, so
    * this is the single place to audit before enabling the package.
    */
   public function build(IssueGroup $group, string $periodDescription): string
   {
      $lines = [
         'Exception: '.($group->exceptionClass ?? 'none — plain log entry'),
         'Level: '.$group->level->label(),
         'Channels: '.implode(', ', $group->channels()),
         'Occurrences: '.$group->count().' during '.$periodDescription,
         'First seen: '.$group->firstSeen()->toDateTimeString(),
         'Last seen: '.$group->lastSeen()->toDateTimeString(),
      ];

      if (! $this->contextLevel->includesMessage()) {
         return implode("\n", $lines);
      }

      $samples = array_slice($group->samples(), 0, $this->samplesPerIssue);
      $index = 0;

      foreach ($samples as $sample) {
         $rendered = $this->renderSample($sample);

         if ($rendered === null) {
            continue;
         }

         $index++;
         $lines[] = '';
         $lines[] = "Sample {$index}:";
         $lines[] = $rendered;
      }

      if ($index === 0) {
         $lines[] = '';
         $lines[] = 'No sample could be transmitted: every candidate was discarded by redaction.';
      }

      return $this->appendSourceFiles($lines, $this->sourceFiles($samples));
   }

   /**
    * Source files are the bulk of a payload and the first thing to shed when an
    * issue would otherwise blow past its token budget. The message and stack
    * always go out; files are appended only while they fit, and whatever no
    * longer fits is dropped with a note rather than silently.
    *
    * @param  list<string>  $lines
    * @param  array<string, string>  $sources
    */
   private function appendSourceFiles(array $lines, array $sources): string
   {
      $payload = implode("\n", $lines);
      $omitted = 0;

      foreach ($sources as $path => $source) {
         $chunk = "\n\nSource file {$path}:\n{$source}";

         if ($this->maxIssueTokens !== null && $this->estimateTokens($payload.$chunk) > $this->maxIssueTokens) {
            $omitted++;

            continue;
         }

         $payload .= $chunk;
      }

      if ($omitted > 0) {
         $payload .= "\n\n({$omitted} more source ".($omitted === 1 ? 'file' : 'files')
            .' omitted to stay within the token budget.)';
      }

      return $payload;
   }

   /**
    * The application's own files that appear in the samples' stack traces. The
    * cause of a failure often lives in the calling code, not the line that threw,
    * so every involved file is loaded whole rather than a window around one line.
    *
    * @param  list<LogEntry>  $samples
    * @return array<string, string>
    */
   private function sourceFiles(array $samples): array
   {
      if ($this->sourceContext === null) {
         return [];
      }

      $sources = [];

      foreach ($samples as $sample) {
         $sources += $this->sourceContext->forFrames($sample->stackFrames);
      }

      return $sources;
   }

   /**
    * Returns null when redaction masked so much of the sample that sending it
    * would be a gamble rather than a contribution.
    */
   private function renderSample(LogEntry $entry): ?string
   {
      $rawMessage = $this->maxSampleCharacters !== null
         ? mb_substr($entry->message, 0, $this->maxSampleCharacters)
         : $entry->message;

      $message = $this->redactor->redact($rawMessage);

      if ($message->shouldDiscard) {
         return null;
      }

      $parts = [$message->text];

      if ($this->contextLevel->includesStackFrames() && $entry->stackFrames !== []) {
         $frames = [];

         $frameSubset = $this->maxStackFrames !== null
            ? array_slice($entry->stackFrames, 0, $this->maxStackFrames)
            : $entry->stackFrames;

         foreach ($frameSubset as $frame) {
            $redactedFrame = $this->redactor->redact($frame);

            if (! $redactedFrame->shouldDiscard) {
               $frames[] = '  '.$redactedFrame->text;
            }
         }

         if ($frames !== []) {
            $parts[] = "Stack:\n".implode("\n", $frames);
         }
      }

      return implode("\n", $parts);
   }

   /**
    * Deliberately crude: four characters per token is close enough to decide
    * whether another issue still fits inside the configured budget.
    */
   public function estimateTokens(string $payload): int
   {
      return (int) ceil(mb_strlen($payload) / 4);
   }
}
