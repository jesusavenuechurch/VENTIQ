<div>
    <div x-data x-on:open-upgrade-modal.window="$wire.openModal()"></div>

    @if($open)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        x-data="{ show: true }"
        x-show="show"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 dark:bg-black/70" wire:click="close"></div>

        {{-- Panel --}}
        <div
            class="relative w-full max-w-2xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-white/10">
                <div>
                    @if($this->isOnStandard)
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $mode === 'addon' ? 'Add to Your Package' : 'Upgrade Package' }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $mode === 'addon'
                                ? 'Unlock individual features for your current Standard package'
                                : 'Move up to Professional and get everything included'
                            }}
                        </p>
                    @else
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Upgrade Package</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            Unlock more features and capacity for your events
                        </p>
                    @endif
                </div>
                <button wire:click="close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mode tabs — only shown for Standard users --}}
            @if($this->isOnStandard && !$submitted)
            <div class="flex border-b border-gray-100 dark:border-white/10">
                <button
                    wire:click="setMode('addon')"
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors
                        {{ $mode === 'addon'
                            ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/50 dark:bg-primary-900/10'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    ➕ Add to Package
                </button>
                <button
                    wire:click="setMode('upgrade')"
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors
                        {{ $mode === 'upgrade'
                            ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/50 dark:bg-primary-900/10'
                            : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' }}"
                >
                    🚀 Upgrade to Professional
                </button>
            </div>
            @endif

            {{-- Body --}}
            <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">

                @if($submitted)
                    {{-- Success --}}
                    <div class="text-center py-8 space-y-4">
                        <div class="text-6xl">🎉</div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Request Submitted!</h3>
                        <p class="text-gray-500 dark:text-gray-400 max-w-sm mx-auto text-sm">
                            Your request is <strong>pending approval</strong>. Our team will review your
                            payment and activate your
                            {{ $mode === 'addon' ? 'add-ons' : 'new package' }}
                            shortly.
                        </p>
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 text-sm text-amber-800 dark:text-amber-200 text-left">
                            <p class="font-semibold mb-1">⏳ What happens next?</p>
                            <ul class="space-y-1 text-amber-700 dark:text-amber-300">
                                <li>• Admin reviews and confirms your payment</li>
                                @if($mode === 'addon')
                                    <li>• Your selected features are activated on your current package</li>
                                    <li>• No event re-creation needed — features unlock immediately</li>
                                @else
                                    <li>• Your new package activates automatically</li>
                                    <li>• Create your next event under the new package</li>
                                @endif
                            </ul>
                        </div>
                        <button
                            wire:click="close"
                            class="mt-2 inline-flex items-center px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors"
                        >
                            Got it, close
                        </button>
                    </div>

                @elseif($mode === 'addon' && $this->isOnStandard)
                    {{-- ADD-ON MODE --}}

                    @if(count($this->availableAddOns) === 0)
                        <div class="text-center py-6 text-gray-400 text-sm">
                            <p class="text-2xl mb-2">✅</p>
                            <p>You already have all available add-ons.</p>
                            <p class="mt-1">Consider upgrading to Professional for the full experience.</p>
                        </div>
                    @else
                        {{-- Add-on selector --}}
                        <div>
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Select add-ons for your Standard package
                            </p>
                            <div class="space-y-3">
                                @foreach($this->availableAddOns as $key => $addon)
                                    <label
                                        class="flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all
                                            {{ in_array($key, $selectedAddOns)
                                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                                : 'border-gray-200 dark:border-white/10 hover:border-gray-300' }}"
                                    >
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                wire:model.live="selectedAddOns"
                                                value="{{ $key }}"
                                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            />
                                            <div>
                                                <p class="font-medium text-sm text-gray-900 dark:text-white">
                                                    {{ $addon['label'] }}
                                                </p>
                                            </div>
                                        </div>
                                        <span class="text-lg font-black text-primary-600 dark:text-primary-400 shrink-0 ml-4">
                                            M{{ number_format($addon['price'], 2) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Professional nudge --}}
                        <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-4 text-sm">
                            <p class="font-semibold text-purple-800 dark:text-purple-200 mb-1">💡 Considering all add-ons?</p>
                            <p class="text-purple-700 dark:text-purple-300">
                                All add-ons together cost
                                <strong>M{{ number_format($this->allAddOnsTotal, 2) }}</strong>.
                                Professional at <strong>M{{ number_format($this->professionalPrice, 2) }}</strong>
                                includes everything plus 300 tickets, 5 scanners, and priority support.
                            </p>
                            <button
                                wire:click="setMode('upgrade')"
                                class="mt-2 text-purple-700 dark:text-purple-300 underline font-medium text-xs"
                            >
                                Switch to upgrade instead →
                            </button>
                        </div>

                        {{-- Total --}}
                        @if(count($selectedAddOns) > 0)
                        <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-primary-700 dark:text-primary-300 font-medium">Total for selected add-ons</p>
                                <p class="text-xs text-primary-500 dark:text-primary-400">
                                    {{ count($selectedAddOns) }} add-on{{ count($selectedAddOns) > 1 ? 's' : '' }} selected
                                </p>
                            </div>
                            <p class="text-3xl font-black text-primary-700 dark:text-primary-300">
                                M{{ number_format($this->addOnTotal, 2) }}
                            </p>
                        </div>
                        @endif

                        {{-- Payment --}}
                        @if(count($selectedAddOns) > 0)
                        <div class="space-y-3">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Payment Details</p>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                    Payment Method <span class="text-red-500">*</span>
                                </label>
                                <select
                                    wire:model="paymentMethod"
                                    class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"
                                >
                                    <option value="">Select payment method...</option>
                                    @foreach(config('constants.payment_methods') as $key => $method)
                                        @if($key !== 'free')
                                            <option value="{{ $key }}">{{ $method['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('paymentMethod') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                    Transaction Reference <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="paymentReference"
                                    placeholder="e.g. ABC123456"
                                    class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"
                                />
                                @error('paymentReference') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                    Notes <span class="text-gray-400 font-normal">(optional)</span>
                                </label>
                                <textarea
                                    wire:model="notes"
                                    rows="2"
                                    placeholder="Any additional information..."
                                    class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                ></textarea>
                            </div>
                        </div>
                        @endif
                    @endif

                @else
                    {{-- UPGRADE MODE --}}

                    {{-- Starter notice --}}
                    @if($this->isOnStarter)
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-semibold mb-1">ℹ️ Starter package has no add-ons</p>
                        <p class="text-blue-700 dark:text-blue-300">
                            To unlock more features, upgrade to Standard or Professional below.
                        </p>
                    </div>
                    @endif

                    {{-- Package cards --}}
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Select your upgrade</p>
                        <div class="grid grid-cols-1 sm:grid-cols-{{ count($this->upgradePackages) }} gap-3">
                            @forelse($this->upgradePackages as $type => $def)
                                <button
                                    type="button"
                                    wire:click="$set('selectedType', '{{ $type }}')"
                                    class="relative text-left rounded-xl border-2 p-4 transition-all
                                        {{ $selectedType === $type
                                            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                            : 'border-gray-200 dark:border-white/10 hover:border-gray-300 dark:hover:border-white/20' }}"
                                >
                                    @if($selectedType === $type)
                                        <span class="absolute top-2 right-2 w-4 h-4 bg-primary-500 rounded-full flex items-center justify-center">
                                            <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </span>
                                    @endif
                                    <p class="font-bold text-gray-900 dark:text-white text-sm">{{ $def['name'] }}</p>
                                    <p class="text-2xl font-black text-primary-600 dark:text-primary-400 mt-1">
                                        M{{ number_format($def['price'], 2) }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ $def['description'] }}</p>
                                    <div class="mt-2 pt-2 border-t border-gray-100 dark:border-white/10 space-y-0.5">
                                        <p class="text-xs text-gray-600 dark:text-gray-300">🎟 {{ $def['tickets'] }} tickets</p>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">🎁 {{ $def['comp_tickets'] }} comp</p>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">📱 {{ $def['max_scanners'] }} scanners</p>
                                    </div>
                                </button>
                            @empty
                                <div class="text-center py-6 text-gray-400 text-sm col-span-3">
                                    <p class="text-2xl mb-2">🎉</p>
                                    <p>You're already on the highest available package.</p>
                                    <p class="mt-1">Contact us about Enterprise for large-scale events.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Features of selected package --}}
                    @if(count($this->upgradePackages) > 0)
                    <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            Included in {{ $this->selectedDefinition['name'] }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($this->selectedDefinition['features'] as $feature => $enabled)
                                @if($enabled)
                                    <span class="text-xs bg-white dark:bg-white/10 border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">
                                        ✓ {{ ucwords(str_replace('_', ' ', $feature)) }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Payment --}}
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Payment Details</p>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                Payment Method <span class="text-red-500">*</span>
                            </label>
                            <select
                                wire:model="paymentMethod"
                                class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"
                            >
                                <option value="">Select payment method...</option>
                                @foreach(config('constants.payment_methods') as $key => $method)
                                    @if($key !== 'free')
                                        <option value="{{ $key }}">{{ $method['label'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('paymentMethod') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                Transaction Reference <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="paymentReference"
                                placeholder="e.g. ABC123456"
                                class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500"
                            />
                            @error('paymentReference') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                                Notes <span class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <textarea
                                wire:model="notes"
                                rows="2"
                                placeholder="Any additional information..."
                                class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                            ></textarea>
                        </div>

                        {{-- Cost summary --}}
                        <div class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-xl p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm text-primary-700 dark:text-primary-300 font-medium">Total to pay</p>
                                <p class="text-xs text-primary-500 dark:text-primary-400">{{ $this->selectedDefinition['name'] }} package</p>
                            </div>
                            <p class="text-3xl font-black text-primary-700 dark:text-primary-300">
                                M{{ number_format($this->selectedDefinition['price'], 2) }}
                            </p>
                        </div>
                    </div>
                    @endif

                @endif
            </div>

            {{-- Footer --}}
            @if(!$submitted)
            <div class="px-6 py-4 border-t border-gray-100 dark:border-white/10 flex items-center justify-between gap-3">
                <p class="text-xs text-gray-400">
                    @if($mode === 'addon')
                        Features activate once payment is confirmed by our team.
                    @else
                        Package activates once payment is confirmed by our team.
                    @endif
                </p>
                <div class="flex gap-2 shrink-0">
                    <button
                        type="button"
                        wire:click="close"
                        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white border border-gray-200 dark:border-white/10 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>

                    @if($mode === 'addon' && count($selectedAddOns) > 0)
                        <button
                            type="button"
                            wire:click="submitAddOns"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 rounded-lg transition-colors inline-flex items-center gap-2"
                        >
                            <span wire:loading.remove wire:target="submitAddOns">Submit Add-on Request</span>
                            <span wire:loading wire:target="submitAddOns" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"></path>
                                </svg>
                                Submitting...
                            </span>
                        </button>
                    @elseif($mode === 'upgrade' && count($this->upgradePackages) > 0)
                        <button
                            type="button"
                            wire:click="submitUpgrade"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 text-sm font-bold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 rounded-lg transition-colors inline-flex items-center gap-2"
                        >
                            <span wire:loading.remove wire:target="submitUpgrade">Submit Upgrade Request</span>
                            <span wire:loading wire:target="submitUpgrade" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"></path>
                                </svg>
                                Submitting...
                            </span>
                        </button>
                    @endif
                </div>
            </div>
            @endif

            {{-- Enterprise footer note --}}
            <div class="px-6 py-3 bg-gray-50 dark:bg-white/5 border-t border-gray-100 dark:border-white/10 text-center">
                <p class="text-xs text-gray-400">
                    Running a large-scale event?
                    <a href="mailto:hello@ventiq.co.ls" class="underline font-medium text-gray-500 dark:text-gray-300">
                        Contact us about Enterprise →
                    </a>
                </p>
            </div>
        </div>
    </div>
    @endif
</div>