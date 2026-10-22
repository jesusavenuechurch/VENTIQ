{{-- The foot of a certificate: up to two signatures either side of the QR code, the date and the number. --}}
@php
    $signers = $c['signatories'];
    $signature = function (array $s) {
        return view('certificates.partials.signature', ['s' => $s])->render();
    };
    $code = '<img src="' . $c['qr'] . '" width="72" height="72" alt=""><div class="small">Scan to verify · ' . e($c['number']) . '</div>';
    $issued = '<div class="fact-label">Issued</div><div class="fact-value">' . e($c['issued']) . '</div>';
    $number = '<div class="fact-label">Certificate No.</div><div class="fact-value">' . e($c['number']) . '</div><div class="small">ventiq.co.ls/certify</div>';
@endphp
<table class="foot">
    <tr>
        @if (count($signers) === 2)
            <td>{!! $signature($signers[0]) !!}</td>
            <td>{!! $code !!}<div class="small">Issued {{ $c['issued'] }}</div></td>
            <td>{!! $signature($signers[1]) !!}</td>
        @elseif (count($signers) === 1)
            <td>{!! $signature($signers[0]) !!}</td>
            <td>{!! $code !!}</td>
            <td>{!! $issued !!}</td>
        @else
            <td>{!! $issued !!}</td>
            <td><img src="{{ $c['qr'] }}" width="72" height="72" alt=""><div class="small">Scan to verify</div></td>
            <td>{!! $number !!}</td>
        @endif
    </tr>
</table>
