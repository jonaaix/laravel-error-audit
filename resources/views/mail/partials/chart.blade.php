@if ($report->hasChart())
<table class="audit-chart" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<img src="{{ isset($message) ? $message->embedData($report->chartPng, 'error-audit-timeline.png', 'image/png') : $report->chartDataUri() }}"
     alt="{{ __('Errors and warnings over time') }}"
     width="600"
     style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">
</td>
</tr>
</table>
@endif
