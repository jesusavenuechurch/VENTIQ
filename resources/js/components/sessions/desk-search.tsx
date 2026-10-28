import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Loader2, Search, X } from 'lucide-react';

export interface SearchHit {
    id: number;
    title: string;
    date: string | null;
    status: string;
    tracking: boolean;
    match: string | null;
}

const STATUS: Record<string, string> = { draft: 'Coming up', active: 'Live', completed: 'Writing up', reported: 'Report' };

/** Find a session by its title, who led an item, or what was said: results as you type. */
export function DeskSearch({ href }: { href: (hit: SearchHit) => string | null }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchHit[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const timer = useRef<number | undefined>(undefined);
    const box = useRef<HTMLDivElement>(null);

    useEffect(() => {
        window.clearTimeout(timer.current);
        const q = query.trim();
        if (q.length < 2) {
            setResults([]);
            setLoading(false);
            return;
        }
        setLoading(true);
        // Debounced, so typing doesn't send a request per key.
        timer.current = window.setTimeout(async () => {
            try {
                const res = await fetch(`${route('sessions.search')}?${new URLSearchParams({ q })}`, { headers: { Accept: 'application/json' } });
                setResults((await res.json()).results ?? []);
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 250);
    }, [query]);

    // Closes when you click or tab away.
    useEffect(() => {
        const away = (e: MouseEvent | FocusEvent) => {
            if (box.current && !box.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', away);
        document.addEventListener('focusin', away);
        return () => {
            document.removeEventListener('mousedown', away);
            document.removeEventListener('focusin', away);
        };
    }, []);

    const showing = open && query.trim().length >= 2;

    return (
        <div ref={box} className="relative w-full">
            <label className="flex items-center gap-2.5 rounded-2xl border border-gray-100 bg-white px-4 py-2.5 shadow-sm focus-within:border-brand/30">
                {loading ? <Loader2 className="size-4 shrink-0 animate-spin text-gray-300" /> : <Search className="size-4 shrink-0 text-gray-300" />}
                <input
                    type="search"
                    value={query}
                    onChange={(e) => { setQuery(e.target.value); setOpen(true); }}
                    onFocus={() => setOpen(true)}
                    onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}
                    placeholder="Search meetings, people, notes…"
                    aria-label="Search sessions"
                    className="min-w-0 flex-1 bg-transparent text-[13px] font-bold text-brand outline-none placeholder:font-medium placeholder:text-gray-300 [&::-webkit-search-cancel-button]:hidden"
                />
                {query && (
                    <button type="button" onClick={() => { setQuery(''); setOpen(false); }} aria-label="Clear the search" className="text-gray-300 hover:text-brand"><X className="size-4" /></button>
                )}
            </label>
            {showing && (
                <div className="absolute inset-x-0 top-full z-30 mt-2 max-h-[22rem] overflow-y-auto rounded-2xl border border-gray-100 bg-white p-1.5 shadow-xl">
                    {results.map((r) => {
                        const to = href(r);
                        const body = (
                            <>
                                <span className="flex items-baseline justify-between gap-3">
                                    <span className="truncate text-[13px] font-bold text-brand">{r.title}</span>
                                    <span className="shrink-0 text-[9px] font-black uppercase tracking-widest text-gray-400">{STATUS[r.status] ?? r.status}</span>
                                </span>
                                <span className="block truncate text-[11px] text-gray-400">{[r.date, r.match].filter(Boolean).join(' · ')}</span>
                            </>
                        );
                        return to ? (
                            <Link key={r.id} href={to} className="block rounded-xl px-3 py-2 hover:bg-gray-50">{body}</Link>
                        ) : (
                            <div key={r.id} className="rounded-xl px-3 py-2">{body}</div>
                        );
                    })}
                    {!loading && results.length === 0 && <p className="px-3 py-4 text-center text-[11px] italic text-gray-400">Nothing found for "{query.trim()}"</p>}
                </div>
            )}
        </div>
    );
}
