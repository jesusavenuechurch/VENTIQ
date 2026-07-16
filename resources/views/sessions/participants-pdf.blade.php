<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 11px; color: #1D4069; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    p.meta { font-size: 10px; color: #888; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #888; border-bottom: 2px solid #1D4069; padding: 6px 4px; }
    td { padding: 6px 4px; border-bottom: 1px solid #eee; font-size: 10px; }
</style>
</head>
<body>
    <h1>{{ $orgName }} — {{ $session->resolved_title }}</h1>
    <p class="meta">Participant List · {{ $participants->count() }} attended · Generated {{ now()->format('d M Y, H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Institution</th>
                <th>Position</th>
                <th>Checked In</th>
            </tr>
        </thead>
        <tbody>
            @foreach($participants as $p)
                <tr>
                    <td>{{ $p->client->full_name }}</td>
                     <td>{{ $p->client->email ?: '—' }}</td>
                    <td>{{ $p->client->phone ?: '—' }}</td>
                    <td>{{ $p->institution ?: '—' }}</td>
                    <td>{{ $p->position ?: '—' }}</td>
                    <td>{{ $p->attended_at?->format('g:i A') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>