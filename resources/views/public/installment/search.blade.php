@extends('layouts.attendee')

@section('title', 'Find my ticket | VENTIQ')

@php $input = 'w-full bg-white border-2 border-gray-100 rounded-2xl px-4 py-3.5 text-[15px] font-bold text-gray-900 outline-none focus:border-[#F07F22] transition-colors'; @endphp

@section('content')
<section class="rounded-[1.5rem] bg-white border border-gray-100 shadow-sm p-6 sm:p-8">
    <span class="mb-4 w-14 h-14 rounded-2xl bg-action-soft text-action-ink flex items-center justify-center"><i class="fas fa-ticket text-xl"></i></span>
    <h1 class="text-2xl font-black">Find my ticket</h1>
    <p class="mt-1 text-[14px] text-gray-500">Enter the phone you registered with to open your ticket: pay, pay the rest, or show it at the door.</p>

    @if ($errors->any())
        <div role="alert" class="mt-5 p-4 rounded-2xl bg-rose-50 border border-rose-100 text-[13px] font-bold text-rose-700">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('installment.find') }}" class="mt-5 space-y-5">
        @csrf
        <div>
            <label for="find_phone" class="block text-[12px] font-black text-gray-600 mb-1.5">Phone number</label>
            <div class="flex">
                <span class="inline-flex items-center px-3.5 rounded-l-2xl border-2 border-r-0 border-gray-100 bg-slate-50 text-[13px] font-black text-gray-500">+266</span>
                <input id="find_phone" type="tel" name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel-national" placeholder="5949 4756" class="{{ $input }} rounded-l-none">
            </div>
        </div>
        <div>
            <label for="find_code" class="block text-[12px] font-black text-gray-600 mb-1.5">Ticket number or entry code</label>
            <input id="find_code" type="text" name="ticket_number" value="{{ old('ticket_number') }}" required autocapitalize="characters" placeholder="e.g. VQ-X82L or TKT-6-AB12CD34" class="{{ $input }} font-mono">
            <p class="mt-1.5 text-[12px] text-gray-500">In your registration email or WhatsApp message, or on the page you saw after registering.</p>
        </div>
        <button type="submit" class="w-full py-4 rounded-2xl bg-[#F07F22] hover:bg-[#1D4069] text-white text-[12px] font-black uppercase tracking-widest">
            <i class="fas fa-magnifying-glass mr-1"></i>Find my ticket
        </button>
    </form>

    <p class="mt-6 pt-5 border-t border-gray-100 text-center text-[12px] text-gray-500">Can't find either? Ask the event's organizer to send your ticket again.</p>
</section>
@endsection
