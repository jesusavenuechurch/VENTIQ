<div class="space-y-6">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Ticket Number</p>
            <p class="text-lg font-semibold">{{ $ticket->ticket_number }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Client</p>
            <p class="text-lg font-semibold">{{ $ticket->client->full_name }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Event</p>
            <p class="text-lg font-semibold">{{ $ticket->event->name }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Tier</p>
            <p class="text-lg font-semibold">{{ $ticket->tier->tier_name }}</p>
        </div>
    </div>

    <div class="border-t dark:border-gray-700 pt-4">
        <h4 class="font-semibold mb-4">Payment Information</h4>
        
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Amount</p>
                <p class="text-2xl font-bold text-green-600">{{ number_format($ticket->amount) }} LSL</p>
            </div>
            
            <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                <div class="mt-1">
                    @if ($ticket->payment_status === 'completed')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            ✅ Completed
                        </span>
                    @elseif ($ticket->payment_status === 'pending')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            ⏳ Pending
                        </span>
                    @elseif ($ticket->payment_status === 'failed')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            ❌ Failed
                        </span>
                    @endif
                </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Method</p>
                <p class="text-lg font-semibold capitalize">{{ $ticket->payment_method }}</p>
            </div>

            <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded">
                <p class="text-sm text-gray-600 dark:text-gray-400">Date</p>
                <p class="text-lg font-semibold">
                    {{ $ticket->payment_date ? $ticket->payment_date->format('M d, Y H:i') : 'Pending' }}
                </p>
            </div>
        </div>

        @if ($ticket->payment_reference)
            <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded border border-blue-200 dark:border-blue-800">
                <p class="text-sm text-gray-600 dark:text-gray-400">Reference</p>
                <p class="font-mono text-sm">{{ $ticket->payment_reference }}</p>
            </div>
        @endif
    </div>
</div>