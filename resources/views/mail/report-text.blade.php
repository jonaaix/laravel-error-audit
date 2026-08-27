{{ $report->applicationName }} — {{ __('error audit') }}
{{ $report->periodStart->format('d.m.Y H:i') }} – {{ $report->periodEnd->format('d.m.Y H:i') }}
{{ __('Status') }}: {{ $report->statusWord() }}

{{ __('Errors') }}: {{ $report->errorCount }}

{{ __('Warnings') }}: {{ $report->warningCount }}

{{ __('Issue types') }}: {{ $report->issueTypeCount() }}
@if ($report->isEmpty())

{{ __('No errors or warnings were logged during this period.') }}
@else

@php
   $sections = $report->issuesByChannel();
   $queueChannel = \Aaix\LaravelErrorAudit\Sources\FailedJobsSource::CHANNEL;
@endphp
{{ __('Issues') }}
@foreach ($sections as $channel => $issues)
@if (count($sections) > 1)

==================================================
{{ $channel === $queueChannel ? __('QUEUE — failed jobs') : strtoupper($channel).' — '.__('log channel') }}
==================================================
@endif
@foreach ($issues as $issue)

--------------------------------------------------
{{ $issue->group->title() }} x{{ $issue->group->count() }}

{{ implode(', ', $issue->group->channels()) }} · {{ $issue->group->lastSeen()->format('d.m. H:i') }}
@if ($issue->assessment)
{{ __('Urgency') }}: {{ $issue->assessment->urgency->label() }} · {{ $issue->assessment->category->label() }}
{{ $issue->assessment->title }}
{{ __('Cause') }}: {{ $issue->assessment->likelyCause }}
{{ __('Suggestion') }}: {{ $issue->assessment->suggestedAction }}
@elseif ($issue->outcome->explanation())
{{ $issue->outcome->explanation() }}
@endif
@endforeach
@endforeach
@endif

--------------------------------------------------
{{ $report->analysisFootnote() }}
