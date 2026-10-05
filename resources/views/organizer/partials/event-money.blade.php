{{-- One event's headcount and money, split by who collected it. $finance
     is EventFinance::summary(). --}}
@php $m = fn ($v) => 'M' . number_format((float) $v, 2); @endphp
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">People</p>
        <div class="grid grid-cols-3 gap-3">
            <div><p class="text-2xl font-black text-[#1D4069]">{{ $finance['people'] }}</p><p class="text-[11px] font-bold text-gray-500">registered</p></div>
            <div><p class="text-2xl font-black text-emerald-600">{{ $finance['people_admitted'] }}</p><p class="text-[11px] font-bold text-gray-500">arrived</p></div>
            <div><p class="text-2xl font-black text-amber-600">{{ $finance['tickets_unpaid'] }}</p><p class="text-[11px] font-bold text-gray-500">unpaid tickets</p></div>
        </div>
        <p class="text-[11px] font-medium text-gray-400 mt-3">
            {{ $finance['tickets'] }} {{ Str::plural('ticket', $finance['tickets']) }}; a group ticket counts every person in it.
            @if($finance['released_tickets']) {{ $finance['released_tickets'] }} expired or cancelled not counted. @endif
        </p>
    </div>

    <div class="bg-white rounded-[1.5rem] border border-gray-100 shadow-sm p-6">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Money</p>
        <dl class="space-y-1.5 text-[13px]">
            <div class="flex justify-between"><dt class="font-bold text-gray-600">Collected by VENTIQ (online)</dt><dd class="font-black text-gray-900">{{ $m($finance['collected_ventiq']) }}</dd></div>
            <div class="flex justify-between"><dt class="font-bold text-gray-600">Collected by you (direct)</dt><dd class="font-black text-gray-900">{{ $m($finance['collected_direct']) }}</dd></div>
            @if($finance['unattributed'] > 0)
                <div class="flex justify-between"><dt class="font-medium text-gray-400">Older payments, source not recorded</dt><dd class="font-bold text-gray-500">{{ $m($finance['unattributed']) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-gray-100 pt-1.5"><dt class="font-black text-[#1D4069]">Total collected</dt><dd class="font-black text-emerald-600">{{ $m($finance['collected']) }}</dd></div>
            @if($finance['awaiting_confirmation'] > 0)
                <div class="flex justify-between"><dt class="font-bold text-[#F07F22]">Waiting for you to confirm</dt><dd class="font-black text-[#F07F22]">{{ $m($finance['awaiting_confirmation']) }}</dd></div>
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
</div>
