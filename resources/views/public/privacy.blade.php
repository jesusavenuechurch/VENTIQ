@extends('layouts.app')

@section('title', 'Privacy policy | VENTIQ')
@section('meta_description', 'How VENTIQ collects, uses and protects the personal information of attendees and event organizers.')

@section('content')
{{-- Public privacy policy (also the URL given to Meta for WhatsApp). Keep it
     true to what the code does; update the date when it changes. --}}
<div class="max-w-2xl mx-auto px-4 py-12 text-[#1D4069]">
    <h1 class="text-3xl font-black tracking-tight">Privacy policy</h1>
    <p class="mt-2 text-[13px] text-gray-500">Effective 7 October 2026</p>

    <div class="mt-8 space-y-7 text-[14px] leading-relaxed text-gray-700">
        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Who we are</h2>
            <p class="mt-1">VENTIQ (ventiq.co.ls) is an event ticketing and check-in platform based in Lesotho. Event organizers use VENTIQ to sell tickets, take payments and check people in; attendees use it to register, pay and receive their tickets. Questions about this policy: <strong>support@ventiq.co.ls</strong>.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">What we collect</h2>
            <p class="mt-1"><strong>If you register for an event:</strong> your name, phone number, and email address if you give one; the ticket you chose and its status; payment details such as the amount, the mobile money or bank reference, and any screenshot you upload as proof of payment; and when your ticket was checked in at the door. For workshops and trainings, the organizer may also ask for your position, institution, district and a signature for the attendance register.</p>
            <p class="mt-2"><strong>If you organize events:</strong> your name and email, how you sign in (password, email code or Google), your organization's details and logo, the payment accounts attendees pay into, and the account VENTIQ pays you out to.</p>
            <p class="mt-2"><strong>When you use the site:</strong> cookies needed to keep you signed in and secure, and when you were last active. If you allow it, your browser's location is used on your device to show events near you; we use it to pick the nearest district and do not store your exact location.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">How we use it</h2>
            <ul class="mt-1 list-disc pl-5 space-y-1">
                <li>To create your ticket, take and confirm your payment, and let you into the event.</li>
                <li>To send you your ticket and messages about it (for example that a payment didn't go through, a reminder before an unpaid place is released, or that your ticket is ready) by WhatsApp, email or both.</li>
                <li>To give organizers what they need to run their event: attendee lists, payment confirmations, attendance registers and reports.</li>
                <li>To pay organizers, work out VENTIQ's fees, and keep financial records.</li>
                <li>To prevent fraud and misuse, such as double payments or fake proofs of payment.</li>
            </ul>
            <p class="mt-2">We don't sell your personal information, and we don't use it for advertising.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">WhatsApp messages</h2>
            <p class="mt-1">VENTIQ sends messages through the WhatsApp Business Platform, run by Meta. We only message the number you gave when registering, and only about your ticket and payments, or, for organizers, about payments to confirm and their events. To stop WhatsApp messages, email support@ventiq.co.ls; you'll still be able to use your ticket link.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Who we share it with</h2>
            <ul class="mt-1 list-disc pl-5 space-y-1">
                <li><strong>The event's organizer</strong> sees the details you gave when registering for their event, your payment status and your check-in.</li>
                <li><strong>Payment providers</strong> (such as PayLesotho for EcoCash payments) receive the phone number and amount needed to take a payment.</li>
                <li><strong>Meta (WhatsApp)</strong> and our <strong>email provider</strong> deliver messages to you.</li>
                <li><strong>Our hosting provider</strong> stores the data on our behalf.</li>
                <li><strong>An AI writing service</strong> may receive event details an organizer chooses to send when using Ventiq Assist to draft an event description. Attendee details are not sent.</li>
                <li><strong>Authorities</strong>, only when the law requires it.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">How we protect it</h2>
            <p class="mt-1">Each ticket has its own private, unguessable link. QR codes, ticket PDFs and proof-of-payment screenshots are kept in private storage and only shown through that link or to the organizer. Organizers only see their own events' attendees, and their team members only what their role allows.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">How long we keep it</h2>
            <p class="mt-1">Tickets and payment records are kept as long as needed for the event, for organizers' records, and for accounting and legal requirements. Organizer accounts that were never used to create anything are warned by email and then removed after two months without activity.</p>
        </section>

        <section id="delete">
            <h2 class="text-[15px] font-black text-[#1D4069]">Your choices and deleting your data</h2>
            <p class="mt-1">You can ask to see, correct or delete the personal information we hold about you. Email <strong>support@ventiq.co.ls</strong> from the email address or with the phone number you used, and tell us what you'd like. We'll reply within 30 days. Some payment records may have to be kept for accounting or legal reasons; we'll tell you if that applies.</p>
            <p class="mt-2">We handle personal information in line with Lesotho's Data Protection Act, 2011.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Children</h2>
            <p class="mt-1">VENTIQ isn't meant to be used by children on their own. A parent or guardian may register a child for an event using their own phone number.</p>
        </section>

        <section>
            <h2 class="text-[15px] font-black text-[#1D4069]">Changes</h2>
            <p class="mt-1">If we change this policy, we'll update it here and change the date at the top.</p>
        </section>

        <p class="pt-2 text-[13px] text-gray-500">See also the <a href="{{ route('terms') }}" class="underline text-[#1D4069] hover:text-[#F07F22]">ticket terms</a>.</p>
    </div>
</div>
@endsection
