<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; color: #1D4069; font-size: 12px; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .date { color: #94a3b8; font-size: 10px; text-transform: uppercase; margin-bottom: 24px; }
    pre { white-space: pre-wrap; font-family: sans-serif; font-size: 12px; line-height: 1.6; color: #334155; }
</style>
</head>
<body>
    <h1>{{ $session->resolved_title }}</h1>
    <p class="date">{{ $session->date?->format('d M Y') ?? $session->created_at->format('d M Y') }}</p>
    <pre>{{ $session->session_report }}</pre>
</body>
</html>