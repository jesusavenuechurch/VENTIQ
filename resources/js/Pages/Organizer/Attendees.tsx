import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, DoorOpen, ExternalLink, FileSpreadsheet, FileText, Gift, Import, MessageCircle, Pencil, Search, Ticket as TicketIcon, Wallet } from 'lucide-react';
import { useState } from 'react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { AppLink } from '@/components/app-link';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { EventMoney, type Finance, type Khoebo } from '@/components/organizer/event-money';
import { ShareButton, ShareEventDialog, type ShareData } from '@/components/organizer/share-event';
import { Pagination, type Paginated } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { avatarUrl } from '@/lib/avatar';
import { cn, money } from '@/lib/utils';
import type { OrganizerProps } from '@/types';

interface Row {
    id: number;
    name: string;
    phone: string;
    updates: boolean;
    code: string;
    tier: string;
    admissions: number;
    admitted: number;
    amount: number;
    balance: number;
    status: string;
    state: string;
    payBy: string | null;
    comp: string | null;
    delivery: string | null;
    ticketUrl: string | null;
}

interface Props {
    event: { id: number; name: string; status: string; isToday: boolean; publicUrl: string | null; feesSponsored: boolean; share: ShareData | null };
    finance: Finance;
    fees: { percent: number; perPerson: number };
    khoebo: Khoebo | null;
    tickets: Paginated<Row>;
    filter: string;
    search: string;
    filters: Record<string, string>;
    payMethods: Record<string, string>;
}

const STATES: Record<string, [string, string]> = {
    to_confirm: ['Payment to confirm', 'bg-action-soft text-action-ink'],
    awaiting: ['Awaiting payment', 'bg-slate-100 text-slate-500'],
    balance_due: ['Active · balance due', 'bg-mint text-mint-ink'],
    active: ['Active', 'bg-mint text-mint-ink'],
    used: ['Used', 'bg-slate-100 text-slate-600'],
    expired: ['Expired', 'bg-rose-50 text-rose-600'],
};

const chip = 'inline-flex items-center gap-1 rounded-full border border-gray-100 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-brand';
const small = 'inline-flex items-center gap-1 rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest';

/** Who registered for an event, where each ticket stands, and the money so far. */
export default function Attendees({ event, finance, fees, khoebo, tickets, filter, search, filters, payMethods }: Props) {
    const { auth, organizer } = usePage<{ organizer: OrganizerProps }>().props;
    const can = organizer.can;
    const [sharing, setSharing] = useState<ShareData | null>(null);
    const [q, setQ] = useState(search);
    const [sending, setSending] = useState<Row | null>(null);
    const [paying, setPaying] = useState<Row | null>(null);
    const [cancelling, setCancelling] = useState<Row | null>(null);

    const list = (params: { filter?: string; q?: string }) =>
        router.get(route('organizer.events.attendees', event.id), { filter: params.filter ?? filter, q: (params.q ?? q) || undefined }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <OrganizerLayout
            title={event.name}
            subtitle="Everyone who's coming, where each ticket stands, and the money so far."
            crumbs={[['Events', route('organizer.home')], [event.name]]}
            draftEventId={event.status === 'draft' ? event.id : null}
        >
            <Head title={`${event.name} · Attendees`} />

            <div className="-mt-4 mb-6 flex flex-wrap gap-2">
                <ShareButton share={event.share} onOpen={setSharing} className="rounded-full bg-brand px-4 py-2 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action" />
                {event.publicUrl && <a href={event.publicUrl} target="_blank" rel="noopener" className={chip}><ExternalLink className="size-3" />Public page</a>}
                <AppLink to="organizer.events.day" params={event.id} className={event.isToday ? cn(chip, 'border-action bg-action text-white hover:bg-action-ink hover:text-white') : chip}>
                    <DoorOpen className="size-3" />Event day
                </AppLink>
                {can.approve_payment && (
                    <>
                        <AppLink to="organizer.events.comp.create" params={{ event: event.id, kind: 'sold' }} className={cn(chip, 'border-action bg-action text-white hover:bg-action-ink hover:text-white')}>
                            <Wallet className="size-3" />Sell a ticket
                        </AppLink>
                        <AppLink to="organizer.events.comp.create" params={event.id} className={chip}><Gift className="size-3" />Complimentary ticket</AppLink>
                        <AppLink to="organizer.events.guests.create" params={event.id} className={chip}><Import className="size-3" />Import guest list</AppLink>
                    </>
                )}
                {can.edit_event && <AppLink to="organizer.events.edit" params={event.id} className={chip}><Pencil className="size-3" />Edit event</AppLink>}
            </div>

            <EventMoney eventId={event.id} finance={finance} fees={fees} superAdmin={!!auth.user?.isSuperAdmin} khoebo={khoebo} />

            {can.view_reports && (
                <div className="mb-6 flex flex-wrap items-center gap-2">
                    <span className="mr-1 text-[10px] font-black uppercase tracking-widest text-gray-400">Download</span>
                    {([['reports.revenue', 'Revenue report'], ['reports.registration-summary', 'Registration summary'], ['reports.attendance', 'Attendance register']] as const).map(([name, text]) => (
                        <a key={name} href={route(name, event.id)} className={cn(small, 'border border-gray-100 bg-white py-1.5 text-brand')}><FileText className="size-3" />{text}</a>
                    ))}
                    <a href={route('reports.attendance-excel', event.id)} className={cn(small, 'border border-gray-100 bg-white py-1.5 text-brand')}><FileSpreadsheet className="size-3 text-mint-ink" />Attendance (Excel)</a>
                </div>
            )}

            <form
                role="search"
                className="mb-4 flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    list({});
                }}
            >
                <div className="relative flex-1">
                    <Search className="absolute left-4 top-1/2 size-3.5 -translate-y-1/2 text-gray-300" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Name, phone, or entry code (VQ-…)"
                        aria-label="Search attendees"
                        className="w-full rounded-full border border-gray-100 bg-white py-3 pl-10 pr-4 text-[13px] font-semibold text-brand outline-none focus:border-action"
                    />
                </div>
                <button className="rounded-full bg-brand px-5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action">Search</button>
                {search !== '' && (
                    <button type="button" onClick={() => { setQ(''); list({ q: '' }); }} className="rounded-full border border-gray-100 bg-white px-4 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand">
                        Clear
                    </button>
                )}
            </form>

            <div className="mb-6 flex flex-wrap gap-2">
                {Object.entries(filters).map(([key, text]) => (
                    <button key={key} type="button" onClick={() => list({ filter: key })} className={cn(small, 'py-1.5', filter === key ? 'bg-brand text-white' : 'border border-gray-100 bg-white text-gray-500 hover:text-brand')}>
                        {text}
                    </button>
                ))}
            </div>

            <div className="divide-y divide-gray-50 rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                {tickets.data.length === 0 ? (
                    <p className="p-10 text-center text-[13px] font-bold text-gray-500">
                        {search !== '' ? `Nobody matches "${search}"${filter !== 'all' ? ' in this group.' : '.'}` : 'Nobody here yet.'}
                    </p>
                ) : (
                    tickets.data.map((t) => {
                        const [stateLabel, badge] = STATES[t.state] ?? [t.state.charAt(0).toUpperCase() + t.state.slice(1), 'bg-slate-100 text-slate-500'];
                        return (
                            <div key={t.id} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                <div className="flex min-w-0 items-center gap-3">
                                    <img src={avatarUrl(t.phone)} alt="" className="size-10 shrink-0 rounded-full bg-slate-100" />
                                    <div className="min-w-0">
                                        <p className="text-[14px] font-black text-brand">
                                            {t.name}
                                            {t.updates && <span title="Agreed to hear about your future events" className="ml-1 rounded-full bg-mint px-2 py-0.5 align-middle text-[9px] font-black uppercase tracking-widest text-mint-ink">Updates</span>}
                                            {t.comp && <span title={t.comp} className="ml-1 rounded-full bg-lilac px-2 py-0.5 align-middle text-[9px] font-black uppercase tracking-widest text-lilac-ink"><Gift className="mr-0.5 inline size-2.5" />Comp</span>}
                                        </p>
                                        <p className="text-[11px] font-semibold text-gray-400">
                                            {t.phone} · <span className="font-mono">{t.code}</span>
                                            {t.delivery === 'failed' && <> · <span className="text-rose-600"><MessageCircle className="inline size-3" /> not delivered</span></>}
                                            {t.delivery && t.delivery !== 'failed' && <> · <span className="text-mint-ink"><MessageCircle className="inline size-3" /> {t.delivery}</span></>}
                                        </p>
                                        <p className="text-[11px] font-medium text-gray-500">
                                            {t.tier}
                                            {t.admissions > 1 && ` · Group of ${t.admissions} (${t.admitted} admitted)`}
                                            {` · ${money(t.amount)}`}
                                            {t.payBy && ` · pay by ${t.payBy}`}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={cn(small, badge)}>{stateLabel}</span>
                                    {t.ticketUrl && (
                                        <>
                                            <a href={t.ticketUrl} target="_blank" rel="noopener" title="Open the attendee's ticket" className={cn(small, 'border border-gray-200 bg-white text-gray-500 hover:text-brand')}><TicketIcon className="size-3" />View</a>
                                            <button type="button" onClick={() => setSending(t)} className={cn(small, 'bg-[#25D366] text-white')}><MessageCircle className="size-3" />Send</button>
                                        </>
                                    )}
                                    {can.approve_payment && t.status === 'pending' && (
                                        <button type="button" onClick={() => setPaying(t)} className={cn(small, 'bg-mint-ink text-white')}><Check className="size-3" />Mark paid</button>
                                    )}
                                    {can.approve_payment && t.status === 'expired' && (
                                        <button type="button" onClick={() => router.post(route('organizer.tickets.reinstate', t.id), {}, { preserveScroll: true })} className={cn(small, 'bg-brand text-white')}>Reinstate</button>
                                    )}
                                    {can.approve_payment && (t.status === 'pending' || t.status === 'expired') && (
                                        <button type="button" onClick={() => setCancelling(t)} className={cn(small, 'border border-gray-200 bg-white text-gray-500 hover:text-rose-600')}>Cancel</button>
                                    )}
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            <div className="mt-6"><Pagination page={tickets} /></div>

            <ShareEventDialog share={sharing} onClose={() => setSharing(null)} />
            {sending && <SendDialog ticket={sending} onClose={() => setSending(null)} />}
            {paying && <MarkPaidDialog ticket={paying} methods={payMethods} onClose={() => setPaying(null)} />}
            <ConfirmDialog
                open={cancelling !== null}
                onOpenChange={(o) => !o && setCancelling(null)}
                title={`Cancel ${cancelling?.name}'s ticket?`}
                confirmLabel="Cancel ticket"
                onConfirm={() => router.post(route('organizer.tickets.cancel', cancelling!.id), {}, { preserveScroll: true, onFinish: () => setCancelling(null) })}
            >
                They will no longer be able to pay for it.
            </ConfirmDialog>
        </OrganizerLayout>
    );
}

/** Send the ticket to WhatsApp again, to a corrected number if that was the problem. */
function SendDialog({ ticket, onClose }: { ticket: Row; onClose: () => void }) {
    const form = useForm({ phone: ticket.phone });
    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="rounded-[2rem]">
                <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('organizer.tickets.resend', ticket.id), { preserveScroll: true, onSuccess: onClose }); }}>
                    <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Send {ticket.name}'s ticket</DialogTitle>
                    <Field label="Send to" htmlFor="send-phone" hint="Fix the number here if it was wrong; it's saved for this attendee.">
                        <Input id="send-phone" inputMode="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} className="rounded-2xl px-4 py-3" />
                    </Field>
                    <Button type="submit" className="w-full bg-[#25D366] py-3 text-[10px] text-white hover:bg-[#1ebe5b]" disabled={form.processing}>
                        <MessageCircle className="size-4" />Send ticket on WhatsApp
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** They reserved a ticket and paid the organizer in person. */
function MarkPaidDialog({ ticket, methods, onClose }: { ticket: Row; methods: Record<string, string>; onClose: () => void }) {
    const form = useForm({ method: 'cash', reference: '' });
    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="rounded-[2rem]">
                <form className="space-y-4 p-7" onSubmit={(e) => { e.preventDefault(); form.post(route('organizer.tickets.paid', ticket.id), { preserveScroll: true, onSuccess: onClose }); }}>
                    <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">{ticket.name} paid you {money(ticket.balance)}</DialogTitle>
                    <Field label="Paid with" htmlFor="paid-method" error={form.errors.method}>
                        <NativeSelect id="paid-method" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                            {Object.entries(methods).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </NativeSelect>
                    </Field>
                    <Field label={<>Receipt or reference <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="paid-ref" error={form.errors.reference}>
                        <Input id="paid-ref" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} className="rounded-2xl px-4 py-3" />
                    </Field>
                    <p className="text-[11px] text-gray-400">The ticket becomes active and is sent to them.</p>
                    <Button type="submit" variant="brand" className="w-full bg-mint-ink py-3 text-[10px]" disabled={form.processing}>Mark as paid</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
