import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, MapPin, X } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Pagination, type Paginated } from '@/components/pagination';
import { cn, money } from '@/lib/utils';

interface Card {
    id: number;
    name: string;
    tagline: string | null;
    organization: string;
    url: string;
    banner: string | null;
    date: string | null;
    venue: string | null;
    from: number | null;
}

interface Filters {
    district: string | null;
    category: string | null;
    when: 'weekend' | 'tonight' | null;
    free: boolean;
    closingSoon: boolean;
}

type Query = Record<string, string | number | boolean | undefined>;

/** Every upcoming listed event, twelve at a time, with quick filters. */
export default function Browse({ events, filters }: { events: Paginated<Card>; filters: Filters }) {
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
                            {events.data.map((e) => (
                                <Link key={e.id} href={e.url} className="group flex flex-col overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm outline-none transition-all duration-500 hover:shadow-2xl hover:shadow-blue-900/10 focus-visible:ring-2 focus-visible:ring-action">
                                    <div className="relative aspect-[16/9] w-full overflow-hidden bg-gray-100">
                                        {e.banner ? (
                                            <img src={e.banner} alt="" loading="lazy" className="size-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                        ) : (
                                            <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand to-brand/80"><span className="text-4xl font-black uppercase italic tracking-tighter text-white/20">Ventiq.</span></div>
                                        )}
                                        {e.from !== null && (
                                            <span className="absolute right-4 top-4 rounded-lg bg-white/90 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-brand shadow-sm backdrop-blur">
                                                {e.from === 0 ? 'Free' : `${money(e.from).replace(/\.00$/, '')}+`}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex flex-grow flex-col p-8">
                                        <p className="mb-3 text-[9px] font-black uppercase tracking-[0.2em] text-action">{e.organization}</p>
                                        <h3 className="mb-2 line-clamp-2 min-h-[56px] text-xl font-black uppercase leading-tight tracking-tight text-brand transition-colors group-hover:text-action">{e.name}</h3>
                                        <p className="mb-6 line-clamp-2 min-h-[40px] text-xs font-medium italic text-gray-500">{e.tagline}</p>
                                        <div className="mb-8 flex-grow space-y-3 text-[11px] font-bold uppercase tracking-tight text-gray-600">
                                            {e.date && <p className="flex items-center gap-2"><CalendarDays className="size-4 text-action" />{e.date}</p>}
                                            {e.venue && <p className="flex items-center gap-2"><MapPin className="size-4 shrink-0 text-brand" /><span className="line-clamp-1">{e.venue}</span></p>}
                                        </div>
                                        <span className="mt-auto w-full rounded-2xl bg-brand px-4 py-4 text-center text-[10px] font-black uppercase tracking-[0.3em] text-white shadow-lg shadow-blue-900/10 transition-all group-hover:bg-action">
                                            Get tickets
                                        </span>
                                    </div>
                                </Link>
                            ))}
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
