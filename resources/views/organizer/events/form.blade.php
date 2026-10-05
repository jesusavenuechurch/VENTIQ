@extends('layouts.app')
@section('title', ($event->exists ? 'Edit ' . $event->name : 'Create event') . ' | VENTIQ')
@section('content')
@php
    $field = 'w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[14px] font-semibold text-gray-900 focus:bg-white focus:border-[#F07F22] outline-none transition-all';
    $label = 'block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1';
    $card  = 'bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6 sm:p-8';
    $driverLabels = collect($onlineDrivers)->map(fn ($d) => config("constants.payment_methods.{$d}.label", ucfirst($d)))->all();
@endphp
<div class="max-w-3xl mx-auto px-4 py-8"
     x-data="{
        name: @js(old('name', $event->name)),
        tagline: @js(old('tagline', $event->tagline)),
        description: @js(old('description', $event->description)),
        mode: @js(old('payment_mode', $event->payment_mode ?? 'free')),
        tiers: @js(array_values($tiers)),
        online: @js($online),
        selected: @js(array_map('intval', $selectedAccounts)),
        installments: @js((bool) old('allow_installments', $event->allow_installments)),
        accounts: @js($accounts->map(fn ($a) => ['id' => $a->id, 'label' => $a->display_label, 'number' => $a->account_number])->values()),
        driverLabels: @js($driverLabels),
        addTier() { this.tiers.push({ tier_name: '', price: null, quantity_available: null, description: '', quantity_per_purchase: 1, color: null, is_active: true }) },
        removeTier(i) { this.tiers.splice(i, 1) },
        get chosenAccounts() { return this.accounts.filter(a => this.selected.includes(a.id)) },
     }"
     x-init="window.Livewire && Livewire.on('ventiq-assist-fill-form', ({ name: n, tagline: t, description: d }) => { if (n) name = n; if (t) tagline = t; if (d) description = d; })">

    @include('organizer.partials.header', [
        'title'    => $event->exists ? 'Edit event' : 'Create an event',
        'subtitle' => $event->exists ? $event->name : 'Fill in the details, choose how people pay, and publish when ready.',
    ])

    @if($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[12px] font-bold text-rose-700">
            <p class="mb-1">Please fix the following:</p>
            <ul class="list-disc ml-5 font-medium">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $event->exists ? route('organizer.events.update', $event) : route('organizer.events.store') }}"
          class="space-y-6">
        @csrf
        @if($event->exists) @method('PUT') @endif

        {{-- ── 1. The basics ─────────────────────────────────────── --}}
        <section class="{{ $card }} space-y-5">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-[13px] font-black text-[#1D4069] uppercase tracking-widest">1 · The basics</h2>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-ventiq-assist-modal'))"
                        class="px-4 py-2 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest">
                    ✨ Write it with Ventiq Assist
                </button>
            </div>

            <div>
                <label class="{{ $label }}" for="name">Event name</label>
                <input id="name" name="name" x-model="name" required maxlength="255" class="{{ $field }}" placeholder="e.g. Maseru Youth Summit">
            </div>
            <div>
                <label class="{{ $label }}" for="tagline">Tagline <span class="normal-case text-gray-300">(optional)</span></label>
                <input id="tagline" name="tagline" x-model="tagline" maxlength="255" class="{{ $field }}" placeholder="One line people see under the name">
            </div>
            <div>
                <label class="{{ $label }}" for="description">Description <span class="normal-case text-gray-300">(optional)</span></label>
                <textarea id="description" name="description" x-model="description" rows="5" class="{{ $field }}"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}" for="category">Category</label>
                    <select id="category" name="category" required class="{{ $field }}">
                        <option value="">Choose…</option>
                        @foreach(config('constants.categories') as $key => $category)
                            <option value="{{ $key }}" @selected(old('category', $event->category) === $key)>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}" for="city">District</label>
                    <select id="city" name="city" required class="{{ $field }}">
                        @foreach(config('constants.districts') as $district)
                            <option value="{{ $district }}" @selected(old('city', $event->city) === $district)>{{ $district }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="{{ $label }}" for="banner">Poster or flyer <span class="normal-case text-gray-300">(optional, up to 10 MB)</span></label>
                @if($event->banner_image)
                    <img src="{{ Storage::url($event->banner_image) }}" alt="Current poster" class="mb-3 h-32 rounded-xl object-cover">
                @endif
                <input id="banner" type="file" name="banner" accept="image/jpeg,image/png,image/webp" class="text-[12px] font-semibold text-gray-600">
            </div>

            <label class="flex items-center gap-3 text-[13px] font-bold text-gray-700">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $event->is_public)) class="w-5 h-5 rounded accent-[#F07F22]">
                Show this event publicly on Ventiq
            </label>
        </section>

        {{-- ── 2. When and where ─────────────────────────────────── --}}
        <section class="{{ $card }} space-y-5">
            <h2 class="text-[13px] font-black text-[#1D4069] uppercase tracking-widest">2 · When and where</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}" for="event_date">Date</label>
                    <input id="event_date" type="date" name="event_date" required class="{{ $field }}"
                           value="{{ old('event_date', $event->event_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}">
                </div>
                <div>
                    <label class="{{ $label }}" for="event_time">Start time</label>
                    <input id="event_time" type="time" name="event_time" required class="{{ $field }}"
                           value="{{ old('event_time', $event->event_date?->format('H:i') ?? '18:00') }}">
                </div>
                <div>
                    <label class="{{ $label }}" for="registration_deadline_date">Registration closes <span class="normal-case text-gray-300">(optional)</span></label>
                    <input id="registration_deadline_date" type="date" name="registration_deadline_date" class="{{ $field }}"
                           value="{{ old('registration_deadline_date', $event->registration_deadline?->format('Y-m-d')) }}">
                </div>
                <div>
                    <label class="{{ $label }}" for="registration_deadline_time">at</label>
                    <input id="registration_deadline_time" type="time" name="registration_deadline_time" class="{{ $field }}"
                           value="{{ old('registration_deadline_time', $event->registration_deadline?->format('H:i') ?? '23:59') }}">
                </div>
                <div>
                    <label class="{{ $label }}" for="venue">Venue <span class="normal-case text-gray-300">(optional)</span></label>
                    <input id="venue" name="venue" maxlength="255" class="{{ $field }}" value="{{ old('venue', $event->venue) }}" placeholder="e.g. Maseru Convention Centre">
                </div>
                <div>
                    <label class="{{ $label }}" for="capacity">Total capacity <span class="normal-case text-gray-300">(optional)</span></label>
                    <input id="capacity" type="number" min="1" name="capacity" class="{{ $field }}" value="{{ old('capacity', $event->capacity) }}">
                </div>
            </div>
            <div>
                <label class="{{ $label }}" for="location">Address <span class="normal-case text-gray-300">(optional)</span></label>
                <textarea id="location" name="location" rows="2" class="{{ $field }}">{{ old('location', $event->location) }}</textarea>
            </div>
        </section>

        {{-- ── 3. Tickets and payment ────────────────────────────── --}}
        <section class="{{ $card }} space-y-6">
            <h2 class="text-[13px] font-black text-[#1D4069] uppercase tracking-widest">3 · Tickets and payment</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach(['free' => ['Free', 'People register at no cost and get their ticket straight away.'], 'paid' => ['Paid', 'People pay for a ticket. You set the prices below.']] as $value => [$title, $hint])
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="{{ $value }}" x-model="mode" class="peer sr-only" @disabled($modeLocked)>
                        <div class="h-full p-5 rounded-2xl border-2 border-slate-100 bg-slate-50 peer-checked:border-[#F07F22] peer-checked:bg-white transition-all {{ $modeLocked ? 'opacity-60' : '' }}">
                            <p class="text-[14px] font-black text-gray-900">{{ $title }}</p>
                            <p class="text-[12px] font-medium text-gray-500 mt-1">{{ $hint }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
            @if($modeLocked)
                <p class="text-[11px] font-medium text-gray-400">People have already registered, so this can no longer switch between free and paid.</p>
            @endif

            <div x-show="mode === 'paid'" x-cloak class="space-y-6">
                {{-- Ticket types --}}
                <div>
                    <p class="{{ $label }}">Ticket types</p>
                    <div class="space-y-3">
                        <template x-for="(tier, i) in tiers" :key="i">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-3">
                                <input type="hidden" :name="`tiers[${i}][id]`" :value="tier.id ?? ''">
                                <div class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                                    <input :name="`tiers[${i}][tier_name]`" x-model="tier.tier_name" placeholder="Name, e.g. VIP" required class="sm:col-span-3 {{ $field }} bg-white">
                                    <input :name="`tiers[${i}][price]`" x-model="tier.price" type="number" min="0" step="0.01" placeholder="Price (M)" required class="sm:col-span-1 {{ $field }} bg-white">
                                    <input :name="`tiers[${i}][quantity_available]`" x-model="tier.quantity_available" type="number" min="1" placeholder="How many (∞)" class="sm:col-span-2 {{ $field }} bg-white">
                                </div>
                                <textarea :name="`tiers[${i}][description]`" x-model="tier.description" rows="1" placeholder="What's included (optional)" class="{{ $field }} bg-white"></textarea>
                                <div class="flex flex-wrap items-center gap-4 text-[12px] font-bold text-gray-600">
                                    <label class="flex items-center gap-2">
                                        People per ticket
                                        <input :name="`tiers[${i}][quantity_per_purchase]`" x-model="tier.quantity_per_purchase" type="number" min="1" max="100" class="w-20 bg-white border border-slate-200 rounded-xl px-3 py-2">
                                    </label>
                                    <label class="flex items-center gap-2">
                                        QR colour
                                        <input type="color" :name="`tiers[${i}][color]`" :value="tier.color || '#3B82F6'" @input="tier.color = $event.target.value" class="w-10 h-8 rounded">
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="hidden" :name="`tiers[${i}][is_active]`" :value="tier.is_active ? 1 : 0">
                                        <input type="checkbox" x-model="tier.is_active" class="w-4 h-4 accent-[#F07F22]"> On sale
                                    </label>
                                    <span x-show="tier.sold" class="text-[11px] text-gray-400" x-text="`${tier.sold} sold`"></span>
                                    <button type="button" @click="removeTier(i)" x-show="tiers.length > 1" class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600"
                                            :title="tier.sold ? 'Already sold, so it will be switched off rather than removed' : ''">Remove</button>
                                </div>
                                <p x-show="tier.quantity_per_purchase > 1" class="text-[11px] font-medium text-gray-500"
                                   x-text="`One ticket and one QR for ${tier.quantity_per_purchase} people; the price is for the whole group.`"></p>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addTier()" class="mt-3 px-4 py-2 rounded-full bg-white border border-gray-200 text-[10px] font-black uppercase tracking-widest text-[#1D4069] hover:border-[#1D4069]">
                        <i class="fas fa-plus mr-1"></i>Add ticket type
                    </button>
                </div>

                {{-- How people pay --}}
                <div class="space-y-3">
                    <p class="{{ $label }}">How people pay</p>

                    @if(count($onlineDrivers))
                        <label class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-100 cursor-pointer">
                            <input type="checkbox" name="online" value="1" x-model="online" @checked($online) class="mt-1 w-5 h-5 accent-[#F07F22]">
                            <span>
                                <span class="block text-[14px] font-black text-gray-900">Pay online through VENTIQ <span class="text-[10px] font-bold text-emerald-600 uppercase">Recommended</span></span>
                                <span class="block text-[12px] font-medium text-gray-500">{{ implode(' or ', $driverLabels) }}. VENTIQ collects the payment and the ticket activates automatically. VENTIQ's fee (4.9% + M7.50 per ticket) comes off at settlement.</span>
                            </span>
                        </label>
                    @else
                        <p class="text-[12px] font-medium text-gray-400">Online payment is switched off on VENTIQ at the moment.</p>
                    @endif

                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <p class="text-[14px] font-black text-gray-900">Pay directly to you</p>
                        <p class="text-[12px] font-medium text-gray-500 mb-3">Attendees pay into your account and submit their reference; you confirm it, then the ticket activates.</p>
                        @forelse($accounts as $account)
                            <label class="flex items-center gap-3 py-1.5 text-[13px] font-bold text-gray-700">
                                <input type="checkbox" name="account_ids[]" value="{{ $account->id }}" x-model.number="selected" @checked(in_array($account->id, array_map('intval', $selectedAccounts))) class="w-4 h-4 accent-[#F07F22]">
                                {{ $account->display_label }}
                                @if($account->account_number)<span class="font-mono text-gray-400 font-medium">{{ $account->account_number }}</span>@endif
                                @if($account->is_default)<span class="text-[9px] font-black uppercase text-[#F07F22]">Default</span>@endif
                            </label>
                        @empty
                            <p class="text-[12px] font-medium text-gray-500">You haven't added any accounts yet.</p>
                        @endforelse
                        <a href="{{ route('organizer.accounts.index') }}" target="_blank" class="inline-block mt-2 text-[10px] font-black uppercase tracking-widest text-[#1D4069] hover:text-[#F07F22]">
                            <i class="fas fa-wallet mr-1"></i>Manage payment accounts
                        </a>
                    </div>

                    {{-- What attendees will see --}}
                    <div class="p-4 rounded-2xl border-2 border-dashed border-slate-200 text-[13px]">
                        <p class="font-black text-gray-800 mb-2">Your attendees will be offered:</p>
                        <template x-if="!online && chosenAccounts.length === 0">
                            <p class="font-bold text-rose-600">Nothing yet. Choose at least one way to pay.</p>
                        </template>
                        <div x-show="online" class="mb-2">
                            <p class="font-bold text-gray-700">Pay online</p>
                            <ul class="list-disc ml-5 text-gray-600"><template x-for="d in driverLabels"><li x-text="`${d} via VENTIQ`"></li></template></ul>
                        </div>
                        <div x-show="chosenAccounts.length">
                            <p class="font-bold text-gray-700">Pay directly to you</p>
                            <ul class="list-disc ml-5 text-gray-600"><template x-for="a in chosenAccounts"><li x-text="a.number ? `${a.label} — ${a.number}` : a.label"></li></template></ul>
                        </div>
                    </div>
                </div>

                {{-- Installments and payment window --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-3 text-[13px] font-bold text-gray-700">
                            <input type="checkbox" name="allow_installments" value="1" x-model="installments" class="w-5 h-5 accent-[#F07F22]">
                            Let people pay a deposit first and the rest later
                        </label>
                    </div>
                    <div x-show="installments" x-cloak>
                        <label class="{{ $label }}" for="minimum_deposit_percentage">Minimum deposit (%)</label>
                        <input id="minimum_deposit_percentage" type="number" min="1" max="100" name="minimum_deposit_percentage" class="{{ $field }}"
                               value="{{ old('minimum_deposit_percentage', $event->minimum_deposit_percentage ?? 30) }}">
                    </div>
                    <div x-show="installments" x-cloak>
                        <label class="{{ $label }}" for="installment_instructions">Instructions for the balance</label>
                        <input id="installment_instructions" name="installment_instructions" class="{{ $field }}" value="{{ old('installment_instructions', $event->installment_instructions) }}">
                    </div>
                    <div>
                        <label class="{{ $label }}" for="payment_window_hours">Hold unpaid tickets for (hours)</label>
                        <input id="payment_window_hours" type="number" min="1" max="720" name="payment_window_hours" class="{{ $field }}"
                               value="{{ old('payment_window_hours', $event->payment_window_hours) }}"
                               placeholder="{{ config('ventiq.payment_window_hours') ? 'Ventiq default: ' . config('ventiq.payment_window_hours') : 'No limit' }}">
                        <p class="text-[11px] font-medium text-gray-400 mt-1 ml-1">Unpaid tickets release their place after this. Leave empty for the default.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── 4. Publish ────────────────────────────────────────── --}}
        <section class="{{ $card }} space-y-4">
            <h2 class="text-[13px] font-black text-[#1D4069] uppercase tracking-widest">4 · Publish</h2>
            @php
                $statuses = $event->exists
                    ? config('constants.event_statuses')
                    : ['draft' => 'Save as draft', 'published' => 'Publish now'];
            @endphp
            <div class="flex flex-wrap gap-2">
                @foreach($statuses as $value => $text)
                    <label class="cursor-pointer">
                        <input type="radio" name="status" value="{{ $value }}" class="peer sr-only" @checked(old('status', $event->status ?? 'draft') === $value)>
                        <span class="block px-4 py-2 rounded-full border-2 border-slate-100 bg-slate-50 text-[11px] font-black uppercase tracking-widest text-gray-600 peer-checked:border-[#F07F22] peer-checked:bg-white peer-checked:text-[#F07F22]">{{ $text }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-[12px] font-medium text-gray-500">Drafts aren't visible to the public. Publishing makes registration open straight away.</p>
        </section>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('organizer.home') }}" class="px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-[#1D4069]">Cancel</a>
            <button class="px-6 py-4 rounded-2xl bg-[#1D4069] hover:bg-[#F07F22] text-white text-[11px] font-black uppercase tracking-[0.2em]">
                {{ $event->exists ? 'Save changes' : 'Create event' }}
            </button>
        </div>
    </form>

    @livewire('ventiq-assist.event-description-assist')
</div>
@endsection
