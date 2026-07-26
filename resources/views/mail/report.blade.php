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
<h2>{{ __('Issues') }}</h2>
@foreach ($report->issues as $issue)
@include('error-audit::mail.partials.issue', ['issue' => $issue, 'report' => $report])
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
