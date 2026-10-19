{{-- One submitted payment and the organizer's three answers. $action is
     where the decision form posts; $canDecide hides the buttons for team
     members without the approve_payment permission. --}}
@php
    $ticket  = $payment->ticket;
    $owed    = max(0, (float) $ticket->amount - (float) $ticket->amount_paid);
    $partial = (float) $payment->amount + 0.009 < $owed;
@endphp
<div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6" x-data="{ rejecting: false }">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <x-avatar :seed="$ticket->client->phone" size="w-12 h-12" />
            <div>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">{{ $ticket->event->name }}</p>
            <p class="text-lg font-black text-[#1D4069] leading-tight mt-1">{{ $ticket->holder_name }}</p>
            <p class="text-[12px] font-medium text-gray-500 mt-1">
                {{ $ticket->tier->tier_name }}
                @if(($ticket->admissions ?? 1) > 1) · Group of {{ $ticket->admissions }} @endif
                · {{ $ticket->ticket_number }}
            </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-2xl font-black text-brand">M{{ number_format((float) $payment->amount, 2) }}</p>
            @if($partial)
                <p class="text-[11px] font-bold text-action-ink">Deposit · M{{ number_format($owed, 2) }} owed in total</p>
            @endif
        </div>
    </div>

    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5 text-[12px]">
        <div>
            <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Paid to</dt>
            <dd class="font-bold text-gray-800 mt-1">
                {{ $payment->paymentAccount?->display_label ?? config('constants.payment_methods.' . $payment->payment_method . '.label', ucfirst((string) $payment->payment_method)) }}
                @if($payment->paymentAccount?->account_number)<span class="block font-mono text-gray-500">{{ $payment->paymentAccount->account_number }}</span>@endif
            </dd>
        </div>
        <div>
            <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Reference</dt>
            <dd class="font-mono font-bold text-gray-800 mt-1 break-all">{{ $payment->payment_reference ?: '—' }}</dd>
            @if($proofUrl ?? null)
                <dd class="mt-1"><a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="text-[11px] font-black text-action-ink hover:underline"><i class="fas fa-image mr-1"></i>View screenshot</a></dd>
            @endif
        </div>
        <div>
            <dt class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Submitted</dt>
            <dd class="font-bold text-gray-800 mt-1">{{ $payment->submitted_at?->diffForHumans() }}</dd>
        </div>
    </dl>

    @if($canDecide)
        <p class="mt-6 text-[12px] font-black text-[#1D4069]">Has this payment been received?</p>
        <form method="POST" action="{{ $action }}" class="mt-3 space-y-3">
            @csrf
            <div class="flex flex-wrap gap-2" x-show="!rejecting">
                <button name="decision" value="activate" class="px-5 py-3 rounded-2xl bg-mint-ink hover:bg-brand text-white text-[10px] font-black uppercase tracking-widest">
                    <i class="fas fa-check mr-1"></i>{{ $partial ? 'Yes — activate with balance due' : 'Yes — activate ticket' }}
                </button>
                @if($partial)
                    <button name="decision" value="deposit" class="px-5 py-3 rounded-2xl bg-mint border-2 border-mint-ink text-mint-ink text-[10px] font-black uppercase tracking-widest">
                        Yes — record deposit, keep inactive
                    </button>
                @endif
                <button type="button" @click="rejecting = true" class="px-5 py-3 rounded-2xl bg-white border border-gray-200 text-gray-600 hover:text-rose-600 text-[10px] font-black uppercase tracking-widest">
                    No — not received
                </button>
            </div>
            <div x-show="rejecting" x-cloak class="space-y-3">
                <textarea name="reason" rows="2" maxlength="500" placeholder="Optional note to the attendee, e.g. 'No payment with this reference reached our account.'"
                          class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-4 py-3 text-[13px] font-medium focus:bg-white focus:border-rose-300 outline-none"></textarea>
                <div class="flex gap-2">
                    <button name="decision" value="reject" class="px-5 py-3 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white text-[10px] font-black uppercase tracking-widest">Reject payment</button>
                    <button type="button" @click="rejecting = false" class="px-5 py-3 rounded-2xl text-gray-500 text-[10px] font-black uppercase tracking-widest">Back</button>
                </div>
            </div>
        </form>
    @endif
</div>
