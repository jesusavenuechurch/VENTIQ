import { Link } from '@inertiajs/react';
import { Doc } from '@/components/site/doc';

/** Plain terms for people buying tickets. VENTIQ sells tickets on behalf of each event's organizer. */
export default function Terms() {
    return (
        <Doc title="Ticket terms" subtitle="These apply when you register for an event on VENTIQ.">
            <section>
                <h2>Who you're dealing with</h2>
                <p>Each event is run by its organizer. VENTIQ provides the registration, tickets and payments on their behalf. Questions about the event itself (programme, venue, changes) go to the organizer.</p>
            </section>
            <section>
                <h2>Your ticket and its link</h2>
                <p>Every ticket has its own private link, sent to you by email or WhatsApp. Use it to pay, send proof of payment and show your ticket at the door. Anyone with the link can see your ticket, so keep it to yourself.</p>
            </section>
            <section>
                <h2>Paying</h2>
                <p>A paid ticket only becomes active once its payment is confirmed. You pay VENTIQ, online or with EcoCash to VENTIQ's merchant number, and VENTIQ passes the money on to the organizer. For some events VENTIQ sponsors, you may pay the organizer directly instead, and they confirm it.</p>
                <p>An unpaid ticket holds your place for a limited time (shown on your ticket, usually 48 hours). If it isn't paid by then, the place is released for someone else.</p>
            </section>
            <section>
                <h2>Refunds and changes</h2>
                <p>Refunds, transfers and cancellations are up to each event's organizer. If you paid twice by mistake, VENTIQ returns the extra payment.</p>
            </section>
            <section>
                <h2>Your details</h2>
                <p>Your name, phone number and email are shared with the event's organizer and used to send your ticket and messages about your payment. Our <Link href={route('privacy')}>privacy policy</Link> explains what we collect and how to have it deleted.</p>
            </section>
        </Doc>
    );
}
