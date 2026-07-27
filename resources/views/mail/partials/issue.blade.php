@php
   use Aaix\LaravelErrorAudit\Sources\FailedJobsSource;
   use Aaix\LaravelErrorAudit\Support\CodePath;

   $assessment = $issue->assessment;
   $level = $issue->group->level;
   $isQueue = in_array(FailedJobsSource::CHANNEL, $issue->group->channels(), true);
   $codePath = CodePath::shorten($issue->group->codePath());
   $prio = $assessment?->urgency->badgeStyle();

   $title = $assessment?->title ?: $issue->group->title();
   $origin = array_filter([$codePath, $issue->group->exceptionClass]);

   $lastSeenTime = $report->formatTimestamp($issue->group->lastSeen());
@endphp
<table class="audit-card" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-left: 4px solid {{ $level->textColor() }};">
<tr>
<td class="audit-card-head">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="audit-card-badges" valign="top">
<span class="audit-badge" style="background-color: {{ $level->tintColor() }}; color: {{ $level->textColor() }};">{{ $level->label() }}</span>@if ($isQueue) <span class="audit-badge audit-badge-queue">&#8635;&nbsp;{{ __('QUEUE') }}</span>@endif @if ($assessment) <span class="audit-badge audit-badge-prio" style="background-color: {{ $prio['background'] }}; color: {{ $prio['text'] }}; border-color: {{ $prio['border'] }};">&#10023;&nbsp;{{ __('PRIO') }}&nbsp;{{ strtoupper($assessment->urgency->label()) }}</span>@endif @if ($issue->isNew) <span class="audit-badge audit-badge-new">{{ __('NEW') }}</span>@endif
</td>
<td class="audit-card-figure" valign="middle" align="right" width="90">
<span class="audit-card-count"><span class="audit-card-count-mark">&times;</span>{{ number_format($issue->group->count(), 0, ',', '.') }}</span>
</td>
</tr>
</table>

<p class="audit-card-title">{{ $title }}</p>
@if ($origin)
<p class="audit-card-origin">{!! implode('<br>', array_map('e', $origin)) !!}</p>
@endif
</td>
</tr>

@if ($assessment)
<tr>
<td class="audit-card-detail">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="audit-card-key" valign="top" width="52">{{ __('Cause') }}</td>
<td class="audit-card-value" valign="top">{{ $assessment->likelyCause }}</td>
</tr>
<tr>
<td class="audit-card-key" valign="top">{{ __('Suggestion') }}</td>
<td class="audit-card-value" valign="top">{{ $assessment->suggestedAction }}</td>
</tr>
</table>
</td>
</tr>
@else
<tr>
<td class="audit-card-detail">
<p class="audit-card-unanalysed">{{ __('Not analysed — beyond the analysis budget for this report. Counted and tracked; it will be analysed on the next run if it persists.') }}</p>
</td>
</tr>
@endif

<tr>
<td class="audit-card-foot">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="audit-card-foot-meta">{{ __('last seen') }} <span class="audit-foot-time">{{ $lastSeenTime }}</span></td>
<td class="audit-card-foot-channel" align="right">{{ __('CHANNEL') }} <span class="audit-card-channel">{{ implode(', ', $issue->group->channels()) }}</span></td>
</tr>
</table>
</td>
</tr>
</table>
