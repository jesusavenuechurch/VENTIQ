{{-- The payout lines in one settlement batch. --}}
@php($items = $settlement->items()->with(['ticket.client', 'ticket.event', 'paymentSession'])->get())
<div class="p-2">
    <table class="w-full text-sm">
        <thead class="text-xs uppercase text-gray-500 border-b">
            <tr>
                <th class="py-2 text-left">Attendee</th>
                <th class="py-2 text-left">Event</th>
                <th class="py-2 text-left">Paid via</th>
                <th class="py-2 text-right">Received</th>
                <th class="py-2 text-right">VENTIQ fee</th>
                <th class="py-2 text-right">To organizer</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($items as $item)
                <tr>
                    <td class="py-2">{{ $item->ticket?->client?->full_name ?? '—' }}</td>
                    <td class="py-2">{{ $item->ticket?->event?->name ?? '—' }}</td>
                    <td class="py-2">{{ ucfirst($item->paymentSession?->payment_method ?? '—') }} · {{ $item->paymentSession?->transaction_id ?? '' }}</td>
                    <td class="py-2 text-right">M{{ number_format((float) $item->amount_received, 2) }}</td>
                    <td class="py-2 text-right text-red-600">−M{{ number_format((float) $item->gateway_fee, 2) }}</td>
                    <td class="py-2 text-right font-bold">M{{ number_format((float) $item->amount_owed_to_org, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t font-bold">
            <tr>
                <td class="py-2" colspan="3">{{ $items->count() }} {{ \Illuminate\Support\Str::plural('payment', $items->count()) }}</td>
                <td class="py-2 text-right">M{{ number_format((float) $items->sum('amount_received'), 2) }}</td>
                <td class="py-2 text-right text-red-600">−M{{ number_format((float) $items->sum('gateway_fee'), 2) }}</td>
                <td class="py-2 text-right">M{{ number_format((float) $items->sum('amount_owed_to_org'), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
