{{-- One event's headcount and money, split by who collected it. $finance
     is EventFinance::summary(). --}}
@php $m = fn ($v) => 'M' . number_format((float) $v, 2); @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">People</p>
        <div class="grid grid-cols-3 gap-3">
            <div><p class="text-2xl font-black text-[#1D4069]">{{ $finance['people'] }}</p><p class="text-[11px] font-bold text-gray-500">registered</p></div>
            <div><p class="text-2xl font-black text-mint-ink">{{ $finance['people_admitted'] }}</p><p class="text-[11px] font-bold text-gray-500">arrived</p></div>
            <div><p class="text-2xl font-black text-slate-400">{{ $finance['tickets_unpaid'] }}</p><p class="text-[11px] font-bold text-gray-500">unpaid tickets</p></div>
        </div>
        <p class="text-[11px] font-medium text-gray-400 mt-3">
            {{ $finance['tickets'] }} {{ Str::plural('ticket', $finance['tickets']) }}; a group ticket counts every person in it.
            @if($finance['released_tickets']) {{ $finance['released_tickets'] }} expired or cancelled not counted. @endif
        </p>
    </div>

    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Money</p>
        <dl class="space-y-1.5 text-[13px]">
            <div class="flex justify-between"><dt class="font-bold text-gray-600"><span class="inline-block w-2 h-2 rounded-full bg-lilac-ink mr-2"></span>Collected by VENTIQ (online)</dt><dd class="font-black text-gray-900">{{ $m($finance['collected_ventiq']) }}</dd></div>
            <div class="flex justify-between"><dt class="font-bold text-gray-600"><span class="inline-block w-2 h-2 rounded-full bg-mint-ink mr-2"></span>Collected by you (direct)</dt><dd class="font-black text-gray-900">{{ $m($finance['collected_direct']) }}</dd></div>
            @if($finance['unattributed'] > 0)
                <div class="flex justify-between"><dt class="font-medium text-gray-400">Older payments, source not recorded</dt><dd class="font-bold text-gray-500">{{ $m($finance['unattributed']) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-gray-100 pt-1.5"><dt class="font-black text-[#1D4069]">Total collected</dt><dd class="font-black text-mint-ink">{{ $m($finance['collected']) }}</dd></div>
            @if($finance['awaiting_confirmation'] > 0)
                <div class="flex justify-between"><dt class="font-bold text-action-ink">Waiting for you to confirm</dt><dd class="font-black text-action-ink">{{ $m($finance['awaiting_confirmation']) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="font-bold text-gray-500">Still to be paid by attendees</dt><dd class="font-bold text-gray-500">{{ $m($finance['outstanding']) }}</dd></div>
        </dl>
        @if($finance['collected_ventiq'] > 0)
            <p class="text-[11px] font-medium text-gray-500 mt-4 pt-3 border-t border-gray-100">
                From online payments VENTIQ owes you <strong class="text-gray-800">{{ $m($finance['payout_total']) }}</strong>
                after its {{ $m($finance['ventiq_fee']) }} fee: {{ $m($finance['payout_settled']) }} paid out,
                <strong class="text-gray-800">{{ $m($finance['payout_due']) }}</strong> still to come.
            </p>
        @endif
    </div>

    <div class="md:col-span-2 bg-lilac rounded-[1.5rem] border border-lilac p-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <p class="text-[10px] font-black text-lilac-ink uppercase tracking-widest">VENTIQ fees</p>
            <div class="flex items-center gap-2">
                @if($finance['fees_sponsored_event'])
                    <span class="px-3 py-1 rounded-full bg-white text-lilac-ink text-[10px] font-black uppercase tracking-widest"><i class="fas fa-gift mr-1"></i>Sponsored by VENTIQ</span>
                @endif
                @if(auth()->user()->isSuperAdmin() && isset($event))
                    <form method="POST" action="{{ route('organizer.events.fee-sponsorship', $event) }}"
                          onsubmit="return confirm('{{ $finance['fees_sponsored_event'] ? 'Charge VENTIQ fees on this event again?' : 'Sponsor this event: VENTIQ will not charge its fees?' }}')">
                        @csrf
                        <button class="px-3 py-1 rounded-full bg-white border border-gray-200 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-[#1D4069]">
                            {{ $finance['fees_sponsored_event'] ? 'Stop sponsoring' : 'Sponsor fees' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-[13px]">
            <div><dt class="font-bold text-gray-500">Service fee</dt><dd class="font-black text-gray-900">{{ $m($finance['fees_service']) }}</dd></div>
            <div><dt class="font-bold text-gray-500">Operational fee</dt><dd class="font-black text-gray-900">{{ $m($finance['fees_operational']) }}</dd></div>
            <div><dt class="font-bold text-gray-500">Taken from payout</dt><dd class="font-black text-gray-900">{{ $m($finance['fees_from_payout']) }}</dd></div>
            <div><dt class="font-bold text-gray-500">To be invoiced</dt><dd class="font-black text-lilac-ink">{{ $m($finance['fees_to_invoice'] + $finance['fees_invoiced']) }}</dd></div>
        </dl>
        <p class="text-[11px] font-medium text-lilac-ink/80 mt-3">
            {{ rtrim(rtrim(number_format(config('constants.fees.service_percent') * 100, 2), '0'), '.') }}% of each ticket price plus {{ $m(config('constants.fees.operational_per_person')) }} per person, on every active ticket.
            Fees on online payments come off your payout; fees on tickets paid directly to you, free and complimentary tickets are invoiced.
            @if($finance['fees_sponsored'] > 0) {{ $m($finance['fees_sponsored']) }} of fees on this event are sponsored by VENTIQ and won't be charged. @endif
        </p>
    </div>
</div>
