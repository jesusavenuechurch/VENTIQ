@extends('layouts.app')

@section('title', 'Ticket terms | VENTIQ')

@section('content')
{{-- Plain terms for people buying tickets. VENTIQ sells tickets on behalf
     of each event's organizer. --}}
<div class="max-w-2xl mx-auto px-4 py-12 text-[#1D4069]">
    <h1 class="text-3xl font-black tracking-tight">Ticket terms</h1>
    <p class="mt-2 text-[13px] text-gray-500">These apply when you register for an event on VENTIQ.</p>

    <div class="mt-8 space-y-6 text-[14px] leading-relaxed text-gray-700">
        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Who you're dealing with</h2>
            <p class="mt-1">Each event is run by its organizer. VENTIQ provides the registration, tickets and payments on their behalf. Questions about the event itself (programme, venue, changes) go to the organizer.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Your ticket and its link</h2>
            <p class="mt-1">Every ticket has its own private link, sent to you by email or WhatsApp. Use it to pay, send proof of payment and show your ticket at the door. Anyone with the link can see your ticket, so keep it to yourself.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Paying</h2>
            <p class="mt-1">A paid ticket only becomes active once its payment is confirmed. Online payments go to VENTIQ, which passes them on to the organizer. Payments made directly to the organizer are confirmed by them.</p>
            <p class="mt-2">An unpaid ticket holds your place for a limited time (shown on your ticket, usually 48 hours). If it isn't paid by then, the place is released for someone else.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Refunds and changes</h2>
            <p class="mt-1">Refunds, transfers and cancellations are up to each event's organizer. If you paid twice by mistake, VENTIQ returns the extra payment.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Your details</h2>
            <p class="mt-1">Your name, phone number and email are shared with the event's organizer and used to send your ticket and messages about your payment.</p>
        </section>
    </div>
</div>
@endsection
