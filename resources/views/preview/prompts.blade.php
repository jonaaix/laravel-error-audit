<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Error Audit — prompts</title>
<style>
   :root { color-scheme: light dark; }
   body {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      background: #f4f4f5;
      color: #18181b;
      margin: 0;
      padding: 24px;
      line-height: 1.5;
   }
   .wrap { max-width: 900px; margin: 0 auto; }
   h1 { font-size: 18px; margin: 0 0 4px; }
   .sub { color: #71717a; font-size: 12px; margin: 0 0 24px; }
   .block { background: #fff; border: 1px solid #e4e4e7; border-radius: 8px; margin: 0 0 16px; overflow: hidden; }
   .block-head {
      background: #fafafa; border-bottom: 1px solid #e4e4e7;
      padding: 10px 14px; font-size: 13px; font-weight: 600;
      display: flex; justify-content: space-between; gap: 12px;
   }
   .block-head .meta { color: #71717a; font-weight: 400; }
   .system .block-head { background: #ede9fe; border-color: #ddd6fe; color: #5b21b6; }
   pre {
      margin: 0; padding: 14px; font-size: 12px;
      white-space: pre-wrap; word-break: break-word; overflow-x: auto;
   }
   @media (prefers-color-scheme: dark) {
      body { background: #18181b; color: #e4e4e7; }
      .block { background: #27272a; border-color: #3f3f46; }
      .block-head { background: #1f1f21; border-color: #3f3f46; }
      .system .block-head { background: #3f2d6b; border-color: #5b21b6; color: #ddd6fe; }
   }
</style>
</head>
<body>
<div class="wrap">
   <h1>Error Audit — prompts sent to the AI provider</h1>
   <p class="sub">Period: {{ $period }} · {{ count($prompts) }} {{ Str::plural('issue', count($prompts)) }} · nothing here was sent; this only assembles the prompts.</p>

   <div class="block system">
      <div class="block-head">System instructions <span class="meta">shared by every issue</span></div>
      <pre>{{ $instructions }}</pre>
   </div>

   @foreach ($prompts as $prompt)
   <div class="block">
      <div class="block-head">
         <span>{{ $prompt['title'] }}</span>
         <span class="meta">{{ $prompt['level'] }} · &times;{{ number_format($prompt['count'], 0, ',', '.') }}</span>
      </div>
      <pre>{{ $prompt['payload'] }}</pre>
   </div>
   @endforeach

   @if ($prompts === [])
   <div class="block"><pre>No errors or warnings in this period.</pre></div>
   @endif
</div>
</body>
</html>
