import { Head, usePage } from '@inertiajs/react';
import { AppLink } from '@/components/app-link';
import { ArrowRight, DoorOpen, Download, Gift, Plus, Receipt, Store } from 'lucide-react';
import { useState } from 'react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { Crowd } from '@/components/organizer/crowd';
import { PublishButton } from '@/components/organizer/publish-button';
import { ShareButton, ShareEventDialog, type ShareData } from '@/components/organizer/share-event';
import type { OrganizerProps } from '@/types';
import { money } from '@/lib/utils';

interface EventRow {
    id: number;
    name: string;
    date: string | null;
    status: string;
    isToday: boolean;
    active: number;
    awaiting: number;
    toConfirm: number;
    collected: number;
    sponsored: boolean;
    share: ShareData | null;
}

interface ScannerApp {
    version: string;
    size: string | null;
    playStore: string | null;
    downloadUrl: string | null;
}

/** The organizer's landing page: their events, and the payments waiting on them. */
export default function Home({ events, toConfirm, scannerApp }: { events: EventRow[]; toConfirm: number; scannerApp: ScannerApp | null }) {
    const { auth, organizer } = usePage<{ organizer: OrganizerProps }>().props;
    const [sharing, setSharing] = useState<ShareData | null>(null);
    const firstName = auth.user?.firstName || 'there';
    const mood = toConfirm > 0 ? "A few payments need you, then you're all set." : events.length === 0 ? "Let's get your first event out there." : "Here's how your events are doing.";
    const pill = 'rounded-full px-2.5 py-1';
    const small = 'rounded-full px-4 py-2 text-[10px] font-black uppercase tracking-widest';

    return (
        <OrganizerLayout title={`Lumela, ${firstName}!`} subtitle={mood}>
            <Head title="Events" />

            {toConfirm > 0 && (
                <AppLink to='organizer.payments.index' className="mb-6 flex items-center justify-between gap-4 rounded-[1.5rem] bg-action p-5 text-white shadow-lg transition-all hover:bg-action-ink">
                    <span className="text-[13px] font-black">
                        <Receipt className="mr-2 inline size-4" />
                        {toConfirm} {toConfirm === 1 ? 'person' : 'people'} paid you and {toConfirm === 1 ? 'is' : 'are'} waiting for their ticket
                    </span>
                    <span className="text-[10px] font-black uppercase tracking-widest">Review <ArrowRight className="ml-1 inline size-3" /></span>
                </AppLink>
            )}

            {organizer.can.create_event && (
                <div className="mb-4 flex justify-end">
                    <AppLink to='organizer.events.create' className="rounded-2xl bg-brand px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action">
                        <Plus className="mr-1 inline size-3.5" />Create event
                    </AppLink>
                </div>
            )}

            <div className="space-y-3">
                {events.length === 0 ? (
                    <div className="rounded-[1.5rem] border border-dashed border-gray-200 bg-white p-10 text-center">
                        <Crowd seeds={['host', 'guest-a', 'guest-b']} />
                        <p className="text-[15px] font-black text-brand">Your first event starts here</p>
                        <p className="mt-1 text-[13px] font-medium text-gray-500">It takes a few minutes. We'll help you share it and sell tickets.</p>
                        {organizer.can.create_event && (
                            <AppLink to='organizer.events.create' className="mt-5 inline-block rounded-2xl bg-action px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action-ink">
                                <Plus className="mr-1 inline size-3.5" />Create my first event
                            </AppLink>
                        )}
                    </div>
                ) : (
                    events.map((event) => (
                        // The whole card opens Attendees & money; its buttons sit above that link.
                        <div key={event.id} className="relative flex flex-wrap items-center justify-between gap-4 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-gray-200 hover:shadow-md">
                            <div className="min-w-0">
                                <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{event.date} · {event.status.charAt(0).toUpperCase() + event.status.slice(1)}</p>
                                <AppLink to='organizer.events.attendees' params={event.id} className="mt-1 block truncate text-lg font-black leading-tight text-brand after:absolute after:inset-0 after:rounded-[1.5rem] focus:outline-none">
                                    {event.name}
                                </AppLink>
                                <div className="mt-3 flex flex-wrap gap-2 text-[11px] font-bold">
                                    <span className={`${pill} bg-mint text-mint-ink`}>{event.active} active</span>
                                    <span className={`${pill} bg-slate-100 text-slate-500`}>{event.awaiting} awaiting payment</span>
                                    {event.toConfirm > 0 && (
                                        <AppLink to='organizer.payments.index' params={{ event: event.id }} className={`${pill} relative z-10 bg-action-soft text-action-ink hover:underline`}>{event.toConfirm} to confirm</AppLink>
                                    )}
                                    {event.collected > 0 && <span className={`${pill} bg-mint text-mint-ink`}>{money(event.collected)} collected</span>}
                                    {event.sponsored && <span className={`${pill} bg-lilac text-lilac-ink`}><Gift className="mr-1 inline size-3" />Sponsored by VENTIQ</span>}
                                </div>
                            </div>
                            <div className="relative z-10 flex flex-wrap gap-2">
                                {event.status === 'draft' && organizer.can.edit_event && <PublishButton eventId={event.id} />}
                                {event.isToday && (
                                    <AppLink to='organizer.events.day' params={event.id} className={`${small} bg-action text-white hover:bg-action-ink`}><DoorOpen className="mr-1 inline size-3.5" />Event day</AppLink>
                                )}
                                <AppLink to='organizer.events.attendees' params={event.id} className={`${small} bg-brand text-white hover:bg-action`}>Attendees &amp; money</AppLink>
                                {organizer.can.edit_event && (
                                    <AppLink to='organizer.events.edit' params={event.id} className={`${small} border border-slate-100 bg-slate-50 text-gray-500 hover:bg-white`}>Edit event</AppLink>
                                )}
                                <ShareButton share={event.share} onOpen={setSharing} />
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* The door app, for whoever scans tickets at the event. */}
            {scannerApp && (
                <div className="mt-8 flex flex-wrap items-center gap-5 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm">
                    <img src="/images/favicon_io/android-chrome-192x192.png" alt="" className="size-14 shrink-0 rounded-2xl border border-gray-100 p-1.5" />
                    <div className="min-w-0 flex-1">
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">For the door</p>
                        <p className="mt-0.5 text-[15px] font-black text-brand">VENTIQ Scanner</p>
                        <p className="mt-0.5 text-[12px] font-medium text-gray-500">Scan tickets at the entrance, even without internet. Android phones; your team signs in with their VENTIQ account.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {scannerApp.playStore && (
                            <a href={scannerApp.playStore} target="_blank" rel="noopener" className="rounded-full bg-brand px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action"><Store className="mr-1 inline size-3.5" />Get it on Google Play</a>
                        )}
                        {scannerApp.downloadUrl && (
                            <a href={scannerApp.downloadUrl} title="For phones without Google Play" className={`rounded-full px-4 py-2.5 text-[10px] font-black uppercase tracking-widest ${scannerApp.playStore ? 'border border-gray-200 bg-white text-gray-600 hover:text-brand' : 'bg-action text-white hover:bg-action-ink'}`}>
                                <Download className="mr-1 inline size-3.5" />Download APK · v{scannerApp.version}{scannerApp.size && ` · ${scannerApp.size}`}
                            </a>
                        )}
                    </div>
                </div>
            )}

            <ShareEventDialog share={sharing} onClose={() => setSharing(null)} />
        </OrganizerLayout>
    );
}
