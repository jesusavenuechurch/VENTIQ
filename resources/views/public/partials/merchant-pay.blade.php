{{-- Paying VENTIQ's EcoCash merchant by hand. The payment's SMS reaches VENTIQ
     and is matched on the reference (or the paying number), so the ticket
     usually arrives on its own; the form is for when it doesn't.
     Needs $merchant, $merchantReference, $paidFrom, $owed, $ticket; $idPrefix keeps ids unique. --}}
@php($p = $idPrefix ?? 'merchant')
<div class="rounded-2xl bg-slate-50 border border-slate-100 p-5 mb-5 text-[13px] font-bold text-gray-700 space-y-2">
    <p>On your phone, pay with EcoCash:</p>
    <ol class="list-decimal list-inside space-y-1 text-gray-600">
        <li>Open the EcoCash menu and choose <strong>Pay merchant</strong></li>
        <li>Merchant code <span class="font-mono text-[#1D4069] text-[15px]">{{ $merchant['code'] }}</span> ({{ $merchant['name'] }})</li>
        <li>Amount <span class="font-mono text-[#1D4069]">M{{ number_format($owed, 2) }}</span></li>
        @if($merchantReference)
            <li>For the reference, type <span class="font-mono text-[#F07F22] text-[17px] tracking-widest">{{ $merchantReference }}</span></li>
        @endif
    </ol>
    <p class="text-gray-500 font-medium">Your ticket is sent automatically once your payment reaches us, usually within minutes.</p>
</div>
<form method="POST" action="{{ route('ticket.pay.merchant', $ticket->qr_code) }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <p class="text-[12px] font-bold text-gray-500">Paid from another phone, or no ticket after 15 minutes? Tell us and we'll find it.</p>
    <div>
        <label for="{{ $p }}_phone" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Number you paid from</label>
        <input id="{{ $p }}_phone" name="merchant_phone" type="tel" required value="{{ old('merchant_phone', $paidFrom) }}" placeholder="5949 4756"
            class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] outline-none font-bold text-gray-900">
    </div>
    <div>
        <label for="{{ $p }}_reference" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Approval code from the EcoCash message (if you have it)</label>
        <input id="{{ $p }}_reference" name="merchant_reference" value="{{ old('merchant_reference') }}" placeholder="e.g. MP261008.1608.A38818"
            class="w-full bg-slate-50 border-2 border-slate-50 rounded-2xl px-6 py-4 focus:bg-white focus:border-[#F07F22] outline-none font-bold text-gray-900">
    </div>
    <div>
        <label for="{{ $p }}_proof" class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Or a screenshot of it</label>
        <input id="{{ $p }}_proof" name="proof" type="file" accept="image/*,application/pdf" class="w-full text-[12px] text-gray-500">
    </div>
    <button class="w-full py-5 bg-[#F07F22] hover:bg-[#1D4069] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all">I've paid</button>
</form>
