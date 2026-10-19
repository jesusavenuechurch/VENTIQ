import { Head, router } from '@inertiajs/react';
import { ArrowLeft, X } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Pagination, type Paginated } from '@/components/pagination';
import { EventCard, type EventCardData } from '@/components/site/event-card';
import { cn } from '@/lib/utils';


interface Filters {
    district: string | null;
    category: string | null;
    when: 'weekend' | 'tonight' | null;
    free: boolean;
    closingSoon: boolean;
}

type Query = Record<string, string | number | boolean | undefined>;

/** Every upcoming listed event, twelve at a time, with quick filters. */
export default function Browse({ events, filters }: { events: Paginated<EventCardData>; filters: Filters }) {
    // Keep what the link chose (district, category) and change one thing.
    const current = (): Query => {
        const q = Object.fromEntries(new URLSearchParams(window.location.search)) as Query;
        delete q.page;
        return q;
    };
    const go = (change: Query) => {
        const q = { ...current(), ...change };
        Object.keys(q).forEach((k) => (q[k] === undefined || q[k] === '' || q[k] === false) && delete q[k]);
        router.get(route('events.browse'), q as never, { preserveScroll: true, preserveState: true });
    };

    const quick: [string, boolean, Query][] = [
        ['All events', !filters.free && !filters.when && !filters.closingSoon, { free: undefined, when: undefined, closing_soon: undefined }],
        ['Free', filters.free, { free: filters.free ? undefined : 1 }],
        ['This weekend', filters.when === 'weekend', { when: filters.when === 'weekend' ? undefined : 'weekend' }],
        ['Tonight', filters.when === 'tonight', { when: filters.when === 'tonight' ? undefined : 'tonight' }],
    ];

    return (
        <SiteLayout>
            <Head title="Browse events" />
            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
                <div className="mb-8">
                    <a href="/" className="mb-4 inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gray-400 transition-colors hover:text-brand">
                        <ArrowLeft className="size-3" />Back to home
                    </a>
                    <h1 className="mb-2 text-4xl font-black uppercase italic tracking-tight text-brand">Upcoming <span className="text-action">events.</span></h1>
                    <p className="text-sm font-medium text-gray-500">Find something on near you, and get your ticket in a minute.</p>
                </div>

                <div className="mb-10 flex flex-wrap gap-2">
                    {quick.map(([text, on, change]) => (
                        <button key={text} type="button" onClick={() => go(change)} className={cn('rounded-xl px-5 py-2.5 text-[10px] font-black uppercase tracking-widest transition-all', on ? 'bg-brand text-white shadow-lg shadow-blue-900/20' : 'border border-gray-200 bg-white text-gray-400 hover:border-action hover:text-action')}>
                            {text}
                        </button>
                    ))}
                    {filters.district && <Chip text={filters.district} onClear={() => go({ district: undefined })} />}
                    {filters.category && <Chip text={filters.category} onClear={() => go({ category: undefined })} />}
                    {filters.closingSoon && <Chip text="Closing soon" onClear={() => go({ closing_soon: undefined })} />}
                </div>

                {events.data.length > 0 ? (
                    <>
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                            {events.data.map((e) => <EventCard key={e.id} event={e} />)}
                        </div>
                        <div className="mt-16"><Pagination page={events} /></div>
                    </>
                ) : (
                    <div className="rounded-[2.5rem] border border-dashed border-gray-200 bg-white p-16 text-center shadow-sm">
                        <h3 className="mb-2 text-xl font-black uppercase italic text-brand">Nothing on yet</h3>
                        <p className="mb-8 text-sm font-medium text-gray-400">No upcoming events match. Try fewer filters, or check back soon.</p>
                        <button type="button" onClick={() => router.get(route('events.browse'))} className="text-[10px] font-black uppercase tracking-widest text-action hover:underline">See all events</button>
                    </div>
                )}
            </div>
        </SiteLayout>
    );
}

function Chip({ text, onClear }: { text: string; onClear: () => void }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-xl bg-action-soft px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-action-ink">
            {text}
            <button type="button" onClick={onClear} aria-label={`Remove ${text}`} className="rounded-full hover:text-brand"><X className="size-3" /></button>
        </span>
    );
}
