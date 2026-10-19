import { Link } from '@inertiajs/react';
import { CalendarDays, MapPin } from 'lucide-react';
import { money } from '@/lib/utils';

export interface EventCardData {
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

/** One upcoming event: banner, price from, date and venue. */
export function EventCard({ event }: { event: EventCardData }) {
    return (
        <Link href={event.url} className="group flex flex-col overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-sm outline-none transition-all duration-500 hover:shadow-2xl hover:shadow-blue-900/10 focus-visible:ring-2 focus-visible:ring-action">
            <div className="relative aspect-[16/9] w-full overflow-hidden bg-gray-100">
                {event.banner ? (
                    <img src={event.banner} alt="" loading="lazy" className="size-full object-cover transition-transform duration-700 group-hover:scale-105" />
                ) : (
                    <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand to-brand/80"><span className="text-4xl font-black uppercase italic tracking-tighter text-white/20">Ventiq.</span></div>
                )}
                {event.from !== null && (
                    <span className="absolute right-4 top-4 rounded-lg bg-white/90 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-brand shadow-sm backdrop-blur">
                        {event.from === 0 ? 'Free' : `${money(event.from).replace(/\.00$/, '')}+`}
                    </span>
                )}
            </div>
            <div className="flex flex-grow flex-col p-8">
                <p className="mb-3 text-[9px] font-black uppercase tracking-[0.2em] text-action">{event.organization}</p>
                <h3 className="mb-2 line-clamp-2 min-h-[56px] text-xl font-black uppercase leading-tight tracking-tight text-brand transition-colors group-hover:text-action">{event.name}</h3>
                <p className="mb-6 line-clamp-2 min-h-[40px] text-xs font-medium italic text-gray-500">{event.tagline}</p>
                <div className="mb-8 flex-grow space-y-3 text-[11px] font-bold uppercase tracking-tight text-gray-600">
                    {event.date && <p className="flex items-center gap-2"><CalendarDays className="size-4 text-action" />{event.date}</p>}
                    {event.venue && <p className="flex items-center gap-2"><MapPin className="size-4 shrink-0 text-brand" /><span className="line-clamp-1">{event.venue}</span></p>}
                </div>
                <span className="mt-auto w-full rounded-2xl bg-brand px-4 py-4 text-center text-[10px] font-black uppercase tracking-[0.3em] text-white shadow-lg shadow-blue-900/10 transition-all group-hover:bg-action">
                    Get tickets
                </span>
            </div>
        </Link>
    );
}
