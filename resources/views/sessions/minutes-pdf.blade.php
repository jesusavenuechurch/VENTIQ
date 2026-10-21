@php($h = $minutes['heading'])
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 48px 56px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 11px; line-height: 1.55; }
    .org { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: #64748b; margin: 0; }
    h1 { font-size: 17px; color: #1D4069; margin: 4px 0 2px; }
    .sub { color: #475569; margin: 0 0 14px; }
    table.facts { border-collapse: collapse; margin-bottom: 18px; }
    table.facts td { padding: 2px 16px 2px 0; vertical-align: top; }
    table.facts td.k { color: #64748b; width: 80px; }
    h2 { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #1D4069; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 18px 0 8px; }
    h3 { font-size: 12px; margin: 12px 0 2px; }
    .lead { color: #64748b; font-size: 10px; margin: 0 0 4px; }
    p { margin: 0 0 6px; }
    ul { margin: 2px 0 6px 0; padding-left: 16px; }
    .muted { color: #94a3b8; font-style: italic; }
    table.actions { width: 100%; border-collapse: collapse; }
    table.actions th { text-align: left; font-size: 9px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 4px 6px 4px 0; }
    table.actions td { border-bottom: 1px solid #f1f5f9; padding: 5px 6px 5px 0; vertical-align: top; }
    .done { color: #059669; }
    .confirm { margin-top: 36px; }
    .line { border-bottom: 1px solid #94a3b8; width: 220px; height: 26px; }
    .foot { margin-top: 28px; font-size: 8px; color: #94a3b8; }
</style>
</head>
<body>
    @include('sessions.partials.letterhead', ['logo' => $logo ?? null, 'name' => $h['organization']])
    @include('sessions.partials.minutes-body', ['minutes' => $minutes])

    <p class="foot">Minutes kept with VENTIQ Sessions. Notes typed during the meeting and written up by Ventiq Assist, then reviewed.</p>
</body>
</html>
