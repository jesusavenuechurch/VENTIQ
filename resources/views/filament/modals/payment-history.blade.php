<div class="space-y-4">
    @forelse ($paymentRecords as $record)
        <div class="border dark:border-gray-700 rounded-lg p-4">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <p class="font-semibold">{{ number_format($record->amount) }} LSL</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $record->created_at->format('M d, Y H:i:s') }}
                    </p>
                </div>
                <div>
                    @if ($record->status === 'completed')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                            ✅ Success
                        </span>
                    @elseif ($record->status === 'pending')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            ⏳ Pending
                        </span>
                    @elseif ($record->status === 'failed')
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                            ❌ Failed
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Method</p>
                    <p class="font-semibold capitalize">{{ $record->method }}</p>
                </div>
                <div>
                    <p class="text-gray-600 dark:text-gray-400">Reference</p>
                    <p class="font-mono text-xs">{{ $record->reference ?? 'N/A' }}</p>
                </div>
            </div>

            @if ($record->processed_by)
                <div class="mt-3 pt-3 border-t dark:border-gray-700 text-xs text-gray-600 dark:text-gray-400">
                    Processed by: <strong>{{ $record->processedBy?->name ?? 'System' }}</strong>
                </div>
            @endif
        </div>
    @empty
        <div class="text-center py-8 text-gray-600 dark:text-gray-400">
            <p>No payment records found</p>
        </div>
    @endforelse
</div>