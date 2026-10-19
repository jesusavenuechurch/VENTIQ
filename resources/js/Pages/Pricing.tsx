import { Head } from '@inertiajs/react';
import { ChartColumn, ChevronDown, DoorOpen, Gift, Globe, HandHeart, QrCode, Smartphone, Ticket, Users, UsersRound, Wallet } from 'lucide-react';
import { useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Reveal } from '@/components/site/reveal';

interface Plan {
    key: string;
    label: string;
    price: number | null;
    description: string;
}

interface Props {
    rate: number;
    perPerson: number;
    plans: Plan[];
}

const m = (v: number) => `M${v.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const input = 'w-full rounded-2xl border border-gray-200 bg-white px-5 py-4 text-lg font-black text-brand outline-none transition-all focus:border-action focus:ring-4 focus:ring-action/10';

/** What VENTIQ costs: a fee per ticket for events, monthly plans for Sessions. */
export default function Pricing({ rate, perPerson, plans }: Props) {
    const pct = +(rate * 100).toFixed(2);
    const fee = (price: number, people: number) => Math.round((Math.max(0, price) * rate + Math.max(1, people) * perPerson) * 100) / 100;
    const [price, setPrice] = useState('150');
    const [people, setPeople] = useState('1');
    const calc = fee(+price || 0, +people || 1);

    const examples: [string, number, number][] = [
        ['M100 ticket', 100, 1],
        ['M250 ticket', 250, 1],
        ['Table of 4 at M900', 900, 4],
        ['Free ticket', 0, 1],
    ];

    const included: [React.ElementType, string][] = [
        [Globe, 'Your event page and registration'],
        [Smartphone, 'Payments online or by EcoCash, collected for you'],
        [QrCode, 'QR tickets by WhatsApp and email'],
        [DoorOpen, 'Door scanning and entry codes'],
        [UsersRound, 'Group tickets and deposits'],
        [ChartColumn, 'Event-day dashboard and reports'],
        [Users, 'Your team, with their own roles'],
        [Wallet, 'Weekly payouts to your account'],
    ];

    const questions: [string, string][] = [
        ['Is there a setup or monthly fee for events?', 'No. Creating an account and events is free. You only pay the per-ticket fee when tickets are issued.'],
        ['Do my attendees pay anything extra?', 'No. They pay your ticket price. The fee comes out of it, and online payment costs are covered by VENTIQ.'],
        ['How do I get paid?', 'Attendees pay VENTIQ, online or with EcoCash to our merchant number using their entry code as the reference. We take the fee off and pay the rest to your account every week.'],
        ['What does "per person" mean on group tickets?', `A Table of 4 admits 4 people, so its fee includes ${m(perPerson * 4)} (4 × ${m(perPerson)}) plus ${pct}% of the table's price.`],
        ['Can VENTIQ cover the fees for a good cause?', 'Sometimes we sponsor the fees for community and charity events. Then attendees can pay you directly. Get in touch.'],
    ];

    return (
        <SiteLayout>
            <Head title="Pricing" />
            <div className="mx-auto max-w-6xl px-4 py-14 text-brand sm:px-6">
                <section className="mx-auto max-w-3xl text-center">
                    <span className="inline-block rounded-full bg-mint/60 px-4 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-mint-ink">No monthly fee for events</span>
                    <h1 className="mt-6 text-4xl font-black tracking-tight md:text-6xl">Free to start.<br className="hidden md:block" /> <span className="text-action">You pay only when people come.</span></h1>
                    <p className="mt-5 text-[15px] leading-relaxed text-gray-500">Create your event, sell tickets, take payments and scan people in at the door. VENTIQ takes a small fee on each ticket, and nothing else.</p>
                </section>

                <Reveal className="mt-14 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                    <div className="rounded-[2.5rem] bg-brand p-8 text-white shadow-2xl shadow-blue-900/20 md:p-10">
                        <p className="text-[10px] font-black uppercase tracking-[0.25em] text-white/50">VENTIQ Events · per ticket</p>
                        <div className="mt-4 flex flex-wrap items-baseline gap-x-4">
                            <p className="text-6xl font-black tracking-tight md:text-7xl">{pct}%</p>
                            <p className="text-3xl font-black text-action">+ {m(perPerson)}</p>
                        </div>
                        <p className="mt-4 max-w-md text-[13px] leading-relaxed text-white/70">{pct}% of the ticket price, plus {m(perPerson)} for each person the ticket admits. Free and complimentary tickets: {m(perPerson)} per person only.</p>
                        <ul className="mt-8 grid gap-3 sm:grid-cols-2">
                            {included.map(([Icon, text]) => (
                                <li key={text} className="flex items-center gap-3 text-[12px] font-bold"><Icon className="size-4 shrink-0 text-action" />{text}</li>
                            ))}
                        </ul>
                        <a href={route('org.register.direct')} className="mt-10 inline-block rounded-2xl bg-action px-8 py-4 text-[11px] font-black uppercase tracking-[0.2em] text-white transition-all hover:bg-white hover:text-brand">Create your free account</a>
                    </div>

                    <div className="rounded-[2.5rem] border border-gray-100 bg-white p-8 shadow-sm md:p-10">
                        <p className="text-lg font-black">What would I pay?</p>
                        <p className="text-[12px] text-gray-500">Try your own ticket price.</p>
                        <label htmlFor="calc-price" className="mt-6 block text-[10px] font-black uppercase tracking-widest text-gray-400">Ticket price (M)</label>
                        <input id="calc-price" type="number" min={0} step={10} value={price} onChange={(e) => setPrice(e.target.value)} className={`mt-2 ${input}`} />
                        <label htmlFor="calc-people" className="mt-4 block text-[10px] font-black uppercase tracking-widest text-gray-400">People it admits</label>
                        <input id="calc-people" type="number" min={1} step={1} value={people} onChange={(e) => setPeople(e.target.value)} className={`mt-2 ${input}`} />
                        <div className="mt-6 space-y-2 rounded-2xl bg-gray-50 p-5">
                            <div className="flex justify-between text-[13px]"><span className="font-bold text-gray-500">VENTIQ fee</span><span className="font-black">{m(calc)}</span></div>
                            <div className="flex justify-between text-[13px]"><span className="font-bold text-gray-500">You keep</span><span className="font-black text-mint-ink">{m(Math.max(0, (+price || 0) - calc))}</span></div>
                        </div>
                        <p className="mt-4 text-[11px] text-gray-400">Online payment costs are on us: the fee is all you pay.</p>
                    </div>
                </Reveal>

                <Reveal className="mt-16">
                    <h2 className="text-2xl font-black tracking-tight">A few examples</h2>
                    <div className="mt-5 overflow-x-auto rounded-[2rem] border border-gray-100 bg-white shadow-sm">
                        <table className="w-full min-w-[420px] text-left text-[13px]">
                            <thead className="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                                <tr><th className="px-6 py-4">Ticket</th><th className="px-6 py-4">VENTIQ fee</th><th className="px-6 py-4">You keep</th></tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {examples.map(([label, p, n]) => (
                                    <tr key={label}>
                                        <td className="px-6 py-4 font-bold">{label}</td>
                                        <td className="px-6 py-4">{m(fee(p, n))}</td>
                                        <td className="px-6 py-4 font-bold text-mint-ink">{p > 0 ? m(p - fee(p, n)) : 'M0.00 (free)'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Reveal>

                <Reveal className="mt-16 grid gap-5 md:grid-cols-3">
                    {([
                        [Ticket, 'Paid to VENTIQ', 'Attendees pay online, or with EcoCash to our merchant number using their entry code. We take the fee off and pay the rest to your account every week. Every payment is listed in your Accounts tab.'],
                        [HandHeart, 'Sponsored events', 'For some community and charity events VENTIQ covers the fees. Attendees can then pay you directly, and you confirm their payment in VENTIQ.'],
                        [Gift, 'Free events', `No service fee, just ${m(perPerson)} per person who registers, invoiced to you. Complimentary tickets you give away count the same.`],
                    ] as [React.ElementType, string, string][]).map(([Icon, title, text]) => (
                        <div key={title} className="rounded-[2rem] border border-gray-100 bg-white p-7 shadow-sm">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-action/10 text-action"><Icon className="size-5" /></span>
                            <p className="mt-5 font-black">{title}</p>
                            <p className="mt-2 text-[13px] leading-relaxed text-gray-500">{text}</p>
                        </div>
                    ))}
                </Reveal>

                <Reveal className="mt-16 rounded-[2.5rem] bg-gray-50 p-8 md:p-10">
                    <p className="text-[10px] font-black uppercase tracking-[0.25em] text-action">VENTIQ Sessions</p>
                    <h2 className="mt-2 text-2xl font-black tracking-tight">For regular meetings, trainings and programmes</h2>
                    <p className="mt-2 text-[13px] text-gray-500">Check-in, attendance cards and reports for sessions you run every month. Monthly plans.</p>
                    <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => (
                            <div key={plan.key} className="rounded-[2rem] bg-white p-6 shadow-sm">
                                <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{plan.label}</p>
                                <p className="mt-2 text-2xl font-black">
                                    {plan.price === null ? "Let's talk" : plan.price === 0 ? 'Free' : <>M{plan.price.toLocaleString('en')}<span className="text-[12px] font-bold text-gray-400">/month</span></>}
                                </p>
                                <p className="mt-3 text-[12px] leading-relaxed text-gray-500">{plan.description}</p>
                            </div>
                        ))}
                    </div>
                </Reveal>

                <Reveal className="mx-auto mt-16 max-w-3xl">
                    <h2 className="text-2xl font-black tracking-tight">Questions</h2>
                    <div className="mt-5 divide-y divide-gray-100 rounded-[2rem] border border-gray-100 bg-white shadow-sm">
                        {questions.map(([q, a]) => (
                            <details key={q} className="group px-6 py-5">
                                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-[14px] font-black">{q}<ChevronDown className="size-4 shrink-0 text-gray-400 transition-transform group-open:rotate-180" /></summary>
                                <p className="mt-3 text-[13px] leading-relaxed text-gray-500">{a}</p>
                            </details>
                        ))}
                    </div>
                    <div className="mt-10 flex flex-wrap justify-center gap-3">
                        <a href={route('org.register.direct')} className="rounded-2xl bg-brand px-8 py-4 text-[11px] font-black uppercase tracking-[0.2em] text-white transition-all hover:bg-action">Start for free</a>
                        <button type="button" onClick={() => window.dispatchEvent(new Event('contact-open'))} className="rounded-2xl border border-gray-200 px-8 py-4 text-[11px] font-black uppercase tracking-[0.2em] text-brand transition-all hover:border-brand">Talk to us</button>
                    </div>
                </Reveal>
            </div>
        </SiteLayout>
    );
}
