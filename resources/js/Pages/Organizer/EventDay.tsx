import { Head, useForm, usePoll, usePage } from '@inertiajs/react';
import { AlertTriangle, FileSpreadsheet, MessageCircle, Users } from 'lucide-react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { AppLink } from '@/components/app-link';
import { avatarUrl } from '@/lib/avatar';
import { cn } from '@/lib/utils';
import type { OrganizerProps } from '@/types';

interface Summary {
    expected: number;
    admitted: number;
    still_to_come: number;
    percent: number;
    tickets: number;
    tickets_arrived: number;
    unpaid_people: number;
}

interface Props {
    event: { id: number; name: string; status: string };
    summary: Summary;
    tiers: { name: string; expected: number; admitted: number }[];
    recent: { id: number; name: string; phone: string; tier: string; admissions: number; admitted: number; at: string }[];
    arrivals: { hour: string; people: number }[];
    updated: string;
    whatsapp: { sent: number; unsent: number; failed: { id: number; name: string; phone: string; ticketUrl: string }[] };
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm';
const label = 'text-[10px] font-black uppercase tracking-widest text-gray-400';
const chip = 'inline-flex items-center gap-1 rounded-full border border-gray-100 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-brand';
const n = (v: number) => v.toLocaleString('en-US');

/** The door view: who's in, who's still coming, and tickets that didn't reach people. Refreshes every 30 seconds. */
export default function EventDay({ event, summary: s, tiers, recent, arrivals, updated, whatsapp }: Props) {
    const { organizer } = usePage<{ organizer: OrganizerProps }>().props;
    // The WhatsApp list isn't reloaded: it holds the Send forms.
    usePoll(30000, { only: ['summary', 'tiers', 'recent', 'arrivals', 'updated'] });

    const cheer =
        s.expected === 0 ? null
        : s.admitted === 0 ? 'Doors open soon. Arrivals show up here as tickets are scanned.'
        : s.admitted >= s.expected ? "Everyone's here! Enjoy your event."
        : s.percent >= 75 ? "Nearly everyone's in."
        : s.percent >= 50 ? 'More than half are in!'
        : 'People are arriving. Things are warming up.';
    const maxHour = Math.max(1, ...arrivals.map((a) => a.people));
    const labelEvery = arrivals.length <= 12 ? 1 : Math.ceil(arrivals.length / 6);

    return (
        <OrganizerLayout
            title={event.name}
            subtitle="Event day: who's in, who's still coming, and tickets that didn't reach people."
            crumbs={[['Events', route('organizer.home')], [event.name, route('organizer.events.attendees', event.id)], ['Event day']]}
            draftEventId={event.status === 'draft' ? event.id : null}
        >
            <Head title={`${event.name} · Event day`} />

            <div className="-mt-4 mb-6 flex flex-wrap items-center gap-2">
                <AppLink to="organizer.events.attendees" params={event.id} className={chip}><Users className="size-3" />Attendees &amp; money</AppLink>
                {organizer.can.view_reports && <a href={route('reports.attendance-excel', event.id)} className={chip}><FileSpreadsheet className="size-3 text-mint-ink" />Attendance (Excel)</a>}
                <span className="ml-auto text-[10px] font-bold uppercase tracking-widest text-gray-400" aria-live="polite">
                    <span className="mr-1 inline-block size-1.5 rounded-full bg-mint-ink align-middle" />Live · updated {updated}
                </span>
            </div>

            <div className="mb-4 grid gap-4 lg:grid-cols-3">
                <div className={cn(card, 'lg:col-span-2')}>
                    <p className={label}>People in</p>
                    <p className="mt-2 flex items-baseline gap-2">
                        <span className="text-5xl font-black tracking-tight text-brand">{n(s.admitted)}</span>
                        <span className="text-[15px] font-bold text-gray-400">of {n(s.expected)} expected</span>
                    </p>
                    <div className="mt-4 h-3 overflow-hidden rounded-full bg-slate-100" role="meter" aria-valuemin={0} aria-valuemax={s.expected} aria-valuenow={s.admitted} aria-label="People checked in">
                        <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${s.percent}%` }} />
                    </div>
                    <p className="mt-2 text-[12px] font-semibold text-gray-500">{s.percent}% arrived · {n(s.still_to_come)} still to come</p>
                    {cheer && (
                        <p className={cn('mt-3 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[12px] font-bold', s.admitted >= s.expected ? 'bg-mint text-mint-ink' : 'bg-slate-50 text-brand')}>{cheer}</p>
                    )}
                </div>

                <div className={cn(card, 'grid grid-cols-2 gap-4 lg:grid-cols-1')}>
                    <div>
                        <p className={label}>Tickets scanned</p>
                        <p className="mt-1 text-2xl font-black text-brand">{n(s.tickets_arrived)} <span className="text-[13px] font-bold text-gray-400">of {n(s.tickets)}</span></p>
                    </div>
                    <div>
                        <p className={label}>Not paid yet</p>
                        <p className="mt-1 text-2xl font-black text-brand">{n(s.unpaid_people)} <span className="text-[13px] font-bold text-gray-400">people</span></p>
                        {s.unpaid_people > 0 && <p className="text-[11px] font-medium text-gray-400">Their tickets won't scan until payment is confirmed.</p>}
                    </div>
                </div>
            </div>

            <div className="mb-4 grid gap-4 lg:grid-cols-2">
                <div className={card}>
                    <p className={cn(label, 'mb-4')}>By ticket type</p>
                    <div className="space-y-4">
                        {tiers.length === 0 ? <p className="text-[12px] text-gray-400">No valid tickets yet.</p> : tiers.map((t) => (
                            <div key={t.name}>
                                <div className="flex justify-between text-[12px] font-bold">
                                    <span className="text-brand">{t.name}</span>
                                    <span className="text-gray-500">{t.admitted} / {t.expected}</span>
                                </div>
                                <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full rounded-full bg-brand" style={{ width: `${t.expected ? Math.min(100, Math.floor((t.admitted / t.expected) * 100)) : 0}%` }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className={card}>
                    <p className={cn(label, 'mb-4')}>Arrivals by hour</p>
                    {arrivals.length === 0 ? (
                        <p className="text-[12px] text-gray-400">Nobody has been scanned in yet.</p>
                    ) : (
                        <>
                            <div className="flex h-36 items-end gap-0.5" role="img" aria-label="People arriving per hour">
                                {arrivals.map((slot) => (
                                    <div key={slot.hour} tabIndex={0} className="group relative flex h-full flex-1 flex-col items-center justify-end">
                                        <div className="w-full max-w-8 rounded-t-[4px] bg-brand transition-colors group-hover:bg-action group-focus:bg-action" style={{ height: `${slot.people ? Math.max(4, Math.round((slot.people / maxHour) * 100)) : 0}%` }} />
                                        <span className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-brand px-2 py-1 text-[10px] font-bold text-white group-hover:block group-focus:block">
                                            {slot.hour} · {slot.people} {slot.people === 1 ? 'person' : 'people'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-1 flex gap-0.5 border-t border-slate-100 pt-1 text-[10px] font-bold text-gray-400">
                                {arrivals.map((slot, i) => <span key={slot.hour} className="flex-1 text-center">{i % labelEvery === 0 ? slot.hour : ''}</span>)}
                            </div>
                            <table className="sr-only">
                                <caption>People arriving per hour</caption>
                                <tbody>{arrivals.map((slot) => <tr key={slot.hour}><th>{slot.hour}</th><td>{slot.people}</td></tr>)}</tbody>
                            </table>
                        </>
                    )}
                </div>
            </div>

            <div className={cn(card, 'mb-4')}>
                <p className={cn(label, 'mb-3')}>Latest arrivals</p>
                <div className="divide-y divide-gray-50">
                    {recent.length === 0 ? <p className="text-[12px] text-gray-400">Arrivals appear here as tickets are scanned.</p> : recent.map((t) => (
                        <div key={t.id} className="flex items-center justify-between gap-3 py-2.5">
                            <div className="flex min-w-0 items-center gap-3">
                                <img src={avatarUrl(t.phone)} alt="" className="size-9 shrink-0 rounded-full bg-slate-100" />
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-black text-brand">{t.name}</p>
                                    <p className="text-[11px] font-medium text-gray-500">{t.tier}{t.admissions > 1 && ` · ${t.admitted} of ${t.admissions} in`}</p>
                                </div>
                            </div>
                            <span className="shrink-0 text-[11px] font-bold text-gray-400">{t.at}</span>
                        </div>
                    ))}
                </div>
            </div>

            <div className={card}>
                <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <p className={label}><MessageCircle className="mr-1 inline size-3" />WhatsApp tickets</p>
                    <p className="text-[12px] font-semibold text-gray-500">
                        {whatsapp.sent} delivered
                        {whatsapp.unsent > 0 && ` · ${whatsapp.unsent} not sent yet`}
                        {whatsapp.failed.length > 0 && <> · <span className="font-bold text-rose-600"><AlertTriangle className="inline size-3" /> {whatsapp.failed.length} failed</span></>}
                    </p>
                </div>
                {whatsapp.failed.length === 0 ? (
                    <p className="border-t border-gray-50 pt-3 text-[12px] text-gray-400">No failed deliveries. If someone says they didn't get their ticket, find them in Attendees and send it again.</p>
                ) : whatsapp.failed.map((t) => <FailedRow key={t.id} ticket={t} />)}
            </div>
        </OrganizerLayout>
    );
}

function FailedRow({ ticket }: { ticket: Props['whatsapp']['failed'][number] }) {
    const form = useForm({ phone: ticket.phone });
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-50 py-2.5">
            <div className="min-w-0">
                <p className="text-[13px] font-black text-brand">{ticket.name}</p>
                <p className="text-[11px] font-semibold text-rose-600"><AlertTriangle className="mr-1 inline size-3" />Not delivered to {ticket.phone}</p>
            </div>
            <form className="flex items-center gap-2" onSubmit={(e) => { e.preventDefault(); form.post(route('organizer.tickets.resend', ticket.id), { preserveScroll: true }); }}>
                <input
                    inputMode="tel"
                    aria-label={`WhatsApp number for ${ticket.name}`}
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                    className="w-40 rounded-full bg-slate-50 px-3 py-1.5 text-[12px] font-semibold text-brand focus:bg-white focus:outline-action"
                />
                <button disabled={form.processing} className="rounded-full bg-[#25D366] px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white"><MessageCircle className="mr-1 inline size-3" />Send again</button>
                <a href={ticket.ticketUrl} target="_blank" rel="noopener" className="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-gray-500">View</a>
            </form>
        </div>
    );
}
