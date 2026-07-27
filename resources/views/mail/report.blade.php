<x-mail::layout>
<x-slot:head>
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media (prefers-color-scheme: dark) {
body, .wrapper, .body { background-color: #18181b !important; }
.inner-body { background-color: #27272a !important; border-color: #3f3f46 !important; }
.content-cell, .content-cell p { color: #d4d4d8 !important; }
h1, h2, h3, .audit-title, .audit-card-title, .audit-card-count, .header a { color: #fafafa !important; }
.audit-card { background-color: #27272a !important; border-color: #3f3f46 !important; }
.audit-card-detail, .audit-card-foot { border-color: #3f3f46 !important; }
.audit-card-foot { background-color: #1f1f21 !important; }
.audit-foot-time { color: #fafafa !important; }
.audit-card-value { color: #d4d4d8 !important; }
.audit-card-channel { background-color: #27272a !important; border-color: #3f3f46 !important; color: #d4d4d8 !important; }
.audit-badge-prio { background-color: #27272a !important; border-color: #3f3f46 !important; }
.audit-badge-queue { background-color: #1e1b4b !important; border-color: #4338ca !important; color: #c7d2fe !important; }
.audit-channel-divider-label { color: #fafafa !important; border-color: #52525b !important; }
.audit-channel-divider-hint { color: #a1a1aa !important; }
.audit-channel-divider-count { color: #a1a1aa !important; border-color: #52525b !important; }
.audit-summary-meta, .audit-summary-delta { color: #d4d4d8 !important; }
.audit-summary { background-color: #3f3f46 !important; }
.audit-period, .audit-summary-footnote, .audit-card-origin, .audit-card-foot-meta, .subcopy p { color: #a1a1aa !important; }
.panel-content { background-color: #3f3f46 !important; }
.subcopy { border-color: #3f3f46 !important; }
}
</style>
</x-slot:head>

@include('error-audit::mail.partials.header', ['report' => $report])

@include('error-audit::mail.partials.chart', ['report' => $report])

@if ($report->isEmpty())
<p>{{ __('No errors or warnings were logged during this period.') }}</p>
@else
@php
   $sections = $report->issuesByChannel();
   $queueChannel = \Aaix\LaravelErrorAudit\Sources\FailedJobsSource::CHANNEL;
@endphp
<h2>{{ __('Issues') }}</h2>
@foreach ($sections as $channel => $issues)
@if (count($sections) > 1)
<table class="audit-channel-divider" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="audit-channel-divider-label">@if ($channel === $queueChannel)&#8635;&nbsp;{{ __('QUEUE') }} <span class="audit-channel-divider-hint">· {{ __('failed jobs') }}</span>@else{{ strtoupper($channel) }} <span class="audit-channel-divider-hint">· {{ __('log channel') }}</span>@endif</td>
<td class="audit-channel-divider-count" align="right">{{ count($issues) === 1 ? __('1 issue') : __(':count issues', ['count' => count($issues)]) }}</td>
</tr>
</table>
@endif
@foreach ($issues as $issue)
@include('error-audit::mail.partials.issue', ['issue' => $issue, 'report' => $report])
@endforeach
@endforeach
@endif

<x-slot:subcopy>
<x-mail::subcopy>
{{ __(':analysed of :total issue types analysed by AI.', ['analysed' => $report->analysedIssueCount, 'total' => $report->issueTypeCount()]) }}
@if ($report->analysisCostUsd > 0)
{{ __('Analysis cost: :cost USD', ['cost' => number_format($report->analysisCostUsd, 4)]) }}@if ($report->analysisModel) ({{ $report->analysisModel }})@endif.
@endif
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
