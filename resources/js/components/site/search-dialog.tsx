import { MapPin, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

interface Result {
    url: string;
    name: string;
    subtitle: string | null;
    date: string | null;
    image: string | null;
    is_local: boolean;
}

/** Find an event by name, venue or district; this district's first. */
export function SearchDialog({ open, onOpenChange, district }: { open: boolean; onOpenChange: (open: boolean) => void; district: string }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Result[]>([]);
    const [loading, setLoading] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => {
        window.clearTimeout(timer.current);
        if (query.length < 2) {
            setResults([]);
            setLoading(false);
            return;
        }
        setLoading(true);
        // Debounced, so typing doesn't send a request per key.
        timer.current = window.setTimeout(async () => {
            try {
                const res = await fetch(`${route('events.search')}?${new URLSearchParams({ q: query, district })}`);
                setResults(await res.json());
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);
    }, [query, district]);

    const note = 'p-8 text-center text-[10px] font-bold uppercase tracking-widest';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent showClose={false} className="top-24 max-w-xl translate-y-0 rounded-[2rem]">
                <DialogTitle className="sr-only">Search events</DialogTitle>
                <div className="flex items-center gap-3 border-b border-gray-100 px-6 py-5">
                    <Search className="size-4 text-gray-300" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search events, venues, districts..."
                        className="flex-1 text-sm font-bold text-gray-800 outline-none placeholder:font-medium placeholder:text-gray-300"
                    />
                    <span className="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-gray-300">
                        <MapPin className="size-3 text-action" />{district}
                    </span>
                    <button type="button" onClick={() => onOpenChange(false)} className="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-gray-500">ESC</button>
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {loading ? (
                        <div className={`${note} text-gray-400`}>Searching…</div>
                    ) : query.length < 2 ? (
                        <div className={`${note} text-gray-300`}>Type at least 2 characters</div>
                    ) : results.length === 0 ? (
                        <div className={`${note} text-gray-400`}>No events found</div>
                    ) : (
                        results.map((event) => (
                            <a key={event.url} href={event.url} className="flex items-center gap-4 border-b border-gray-50 px-6 py-3.5 transition-colors last:border-none hover:bg-gray-50">
                                <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand">
                                    {event.image ? <img src={event.image} alt="" className="size-full object-cover" /> : <span className="text-[8px] font-black uppercase text-white/20">VQ</span>}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-black text-gray-900">{event.name}</p>
                                    <p className="truncate text-[11px] font-medium text-gray-400">{[event.subtitle, event.date].filter(Boolean).join(' · ')}</p>
                                </div>
                                {event.is_local === false && (
                                    <span className="shrink-0 rounded-full border border-gray-200 px-2 py-1 text-[8px] font-black uppercase tracking-widest text-gray-300">Other district</span>
                                )}
                            </a>
                        ))
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
