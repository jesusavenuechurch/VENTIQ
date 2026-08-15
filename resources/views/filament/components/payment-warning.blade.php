<div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4 mb-6">
    <div class="flex items-start">
        <svg class="h-6 w-6 text-yellow-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div class="flex-1">
            <h3 class="text-lg font-bold text-yellow-900 mb-2">⚠️ Payment Methods Not Configured</h3>
            <p class="text-yellow-800 mb-3">
                You haven't set up any payment methods yet. If you plan to sell <strong>paid tickets</strong>, 
                you must configure your payment methods first so customers know where to send payments.
            </p>
            <div class="flex gap-3">
                <a href="{{ \App\Filament\Resources\OrganizationPaymentMethodResource::getUrl('create') }}"
                   target="_blank"
                   class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-semibold rounded-lg transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Set Up Payment Methods Now
                </a>
                <span class="text-yellow-700 text-sm self-center">
                    or create free tickets only
                </span>
            </div>
        </div>
    </div>
</div>