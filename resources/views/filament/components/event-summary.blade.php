{{-- resources/views/filament/components/event-summary.blade.php --}}

<div class="space-y-4 bg-gray-50 rounded-lg p-6 border border-gray-200">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <p class="text-sm font-medium text-gray-500">Event Name</p>
            <p class="text-lg font-semibold text-gray-900">{{ $name }}</p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-500">Event Date</p>
            <p class="text-lg font-semibold text-gray-900">
                @if($date !== 'Not set')
                    {{ \Carbon\Carbon::parse($date)->format('M j, Y @ g:i A') }}
                @else
                    Not set
                @endif
            </p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-500">Venue</p>
            <p class="text-lg font-semibold text-gray-900">{{ $venue }}</p>
        </div>

        <div>
            <p class="text-sm font-medium text-gray-500">Ticket Tiers</p>
            <p class="text-lg font-semibold text-gray-900">{{ $tierCount }} tier(s)</p>
        </div>
    </div>

    @if($tierCount > 0)
    <div class="mt-6 pt-6 border-t border-gray-300">
        <p class="text-sm font-medium text-gray-500 mb-3">Ticket Pricing</p>
        <div class="space-y-2">
            @foreach($tiers as $tier)
                <div class="flex items-center justify-between bg-white rounded-lg px-4 py-3 border border-gray-200">
                    <span class="font-medium text-gray-900">{{ $tier['tier_name'] ?? 'Unnamed Tier' }}</span>
                    <span class="text-lg font-bold text-blue-600">
                        @if(isset($tier['price']) && $tier['price'] == 0)
                            FREE
                        @else
                            {{ config('constants.currency.symbol') }} {{ number_format($tier['price'] ?? 0, 2) }}
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Package Summary --}}
    @if(!empty($package))
    <div class="mt-6 pt-6 border-t border-gray-300">
        <p class="text-sm font-medium text-gray-500 mb-3">Package</p>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100">
                <span class="font-semibold text-gray-900">{{ $package->display_name }}</span>
                <span class="text-xs font-medium px-2 py-1 rounded-full
                    @if($package->package_type === 'starter') bg-blue-100 text-blue-700
                    @elseif($package->package_type === 'standard') bg-green-100 text-green-700
                    @elseif($package->package_type === 'professional') bg-purple-100 text-purple-700
                    @elseif($package->package_type === 'enterprise') bg-red-100 text-red-700
                    @else bg-gray-100 text-gray-600
                    @endif
                ">{{ ucfirst($package->package_type) }}</span>
            </div>
            <div class="grid grid-cols-4 divide-x divide-gray-100">
                <div class="px-3 py-3 text-center">
                    <p class="text-lg font-bold text-gray-800">{{ $package->remaining_tickets }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Tickets</p>
                </div>
                <div class="px-3 py-3 text-center">
                    <p class="text-lg font-bold text-gray-800">{{ $package->remaining_comp_tickets }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Comp</p>
                </div>
                <div class="px-3 py-3 text-center">
                    <p class="text-lg font-bold text-gray-800">{{ $package->max_scanners }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Scanners</p>
                </div>
                <div class="px-3 py-3 text-center">
                    <p class="text-lg font-bold text-gray-800">{{ $package->max_users }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">Team</p>
                </div>
            </div>
            @if(!empty($package->getEnabledFeatures()))
            <div class="px-4 py-2 bg-gray-50 border-t border-gray-100">
                <div class="flex flex-wrap gap-2">
                    @foreach($package->getEnabledFeatures() as $feature)
                        <span class="text-xs bg-white border border-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                            ✓ {{ ucwords(str_replace('_', ' ', $feature)) }}
                        </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <div class="mt-6 pt-6 border-t border-gray-300">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-blue-600 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-medium mb-1">Review your event details above</p>
                    <p>Make sure everything looks correct before publishing. You can always edit these details later.</p>
                </div>
            </div>
        </div>
    </div>
</div>