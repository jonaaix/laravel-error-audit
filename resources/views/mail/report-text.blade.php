{{ $report->applicationName }} — {{ __('error audit') }}
{{ $report->periodStart->format('d.m.Y H:i') }} – {{ $report->periodEnd->format('d.m.Y H:i') }}
{{ __('Status') }}: {{ $report->statusWord() }}

{{ __('Errors') }}: {{ $report->errorCount }}@if ($report->errorDeltaPercent() !== null) ({{ $report->errorDeltaPercent() > 0 ? '+' : '' }}{{ $report->errorDeltaPercent() }}%)@endif

{{ __('Warnings') }}: {{ $report->warningCount }}@if ($report->warningDeltaPercent() !== null) ({{ $report->warningDeltaPercent() > 0 ? '+' : '' }}{{ $report->warningDeltaPercent() }}%)@endif

{{ __('Issue types') }}: {{ $report->issueTypeCount() }} ({{ __('new') }}: {{ $report->newIssueTypeCount() }})
@if ($report->isEmpty())

{{ __('No errors or warnings were logged during this period.') }}
@else

{{ __('Issues') }}
@foreach ($report->issues as $issue)

--------------------------------------------------
{{ $issue->group->title() }} x{{ $issue->group->count() }}@if ($issue->isNew) [{{ __('NEW') }}]@endif

{{ implode(', ', $issue->group->channels()) }} · {{ $issue->group->lastSeen()->format('d.m. H:i') }}
@if ($issue->assessment)
{{ __('Urgency') }}: {{ $issue->assessment->urgency->label() }} · {{ $issue->assessment->category->label() }}
{{ $issue->assessment->title }}
{{ __('Cause') }}: {{ $issue->assessment->likelyCause }}
{{ __('Suggestion') }}: {{ $issue->assessment->suggestedAction }}
@else
{{ __('Not analysed in this run.') }}
@endif
@endforeach
@endif

--------------------------------------------------
{{ __(':analysed of :total issue types analysed by AI.', ['analysed' => $report->analysedIssueCount, 'total' => $report->issueTypeCount()]) }}@if ($report->analysisCostUsd > 0) {{ __('Analysis cost: :cost USD', ['cost' => number_format($report->analysisCostUsd, 4)]) }}@endif
