{{-- The organization's letterhead on a session document: its logo (when it has one) beside its name. --}}
<table style="border-collapse: collapse; margin-bottom: 10px;">
    <tr>
        @if ($logo)
            <td style="padding: 0 12px 0 0; vertical-align: middle;"><img src="{{ $logo }}" alt="" style="max-height: 44px; max-width: 160px;"></td>
        @endif
        @if ($name)
            <td style="vertical-align: middle; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #64748b;">{{ $name }}</td>
        @endif
    </tr>
</table>
