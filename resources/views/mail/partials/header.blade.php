@php
   $summaries = [
      [
         'label' => __('Errors'),
         'count' => $report->errorCount,
         'types' => $report->errorTypeCount(),
         'accent' => '#DC2626',
         'tint' => '#fdeaea',
      ],
      [
         'label' => __('Warnings'),
         'count' => $report->warningCount,
         'types' => $report->warningTypeCount(),
         'accent' => '#B45309',
         'tint' => '#fef6e0',
      ],
   ];
@endphp
<h1 class="audit-title">{{ __('Laravel Error Audit') }} &ndash; {{ $report->periodEnd->format('d.m.Y') }} &ndash; {{ $report->periodLabel() }}</h1>
<p class="audit-period">{{ $report->applicationName }} &middot; {{ $report->periodStart->format('d.m.Y H:i') }} &ndash; {{ $report->periodEnd->format('d.m.Y H:i') }}</p>

@foreach ($summaries as $summary)
<table class="audit-summary" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: {{ $summary['tint'] }}; border-left: 4px solid {{ $summary['accent'] }};">
<tr>
<td class="audit-summary-count" width="70" align="right" style="color: {{ $summary['accent'] }};">{{ number_format($summary['count'], 0, ',', '.') }}</td>
<td class="audit-summary-label" style="color: {{ $summary['accent'] }};">{{ $summary['label'] }}</td>
<td class="audit-summary-meta" align="right">{{ trans_choice('{1}:count type|[2,*]:count types', $summary['types'], ['count' => $summary['types']]) }}</td>
</tr>
</table>
@endforeach
