{{-- Horizontal bars for a PDF: [label, count, shown?, light?] rows, the value written beside each. --}}
@php($max = max(1, collect($rows)->max('count') ?? 0))
@if (count($rows))
    <table class="bars">
        @foreach ($rows as $r)
            <tr>
                <td class="l">{{ $r['label'] }}</td>
                <td><div class="track"><div class="fill{{ !empty($r['light']) ? ' light' : '' }}" style="width: {{ max(2, round($r['count'] / $max * 100)) }}%;"></div></div></td>
                <td class="v">{{ $r['shown'] ?? $r['count'] }}@if (!empty($total)) <span style="color: #94a3b8; font-weight: normal;">{{ round($r['count'] / $total * 100) }}%</span>@endif</td>
            </tr>
        @endforeach
    </table>
@else
    <p class="muted">Nothing recorded.</p>
@endif
