import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/** A Laravel paginator as Inertia gets it (->paginate() passed as a prop). */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

// Laravel's labels come as "&laquo; Previous" and "Next &raquo;".
const label = (text: string) => text.replace('&laquo;', '‹').replace('&raquo;', '›');

export function Pagination({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) return null;

    return (
        <nav aria-label="Pages" className="flex flex-wrap items-center justify-center gap-1.5">
            {page.links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        className={cn('min-w-9 rounded-full px-3 py-2 text-center text-[11px] font-black', link.active ? 'bg-brand text-white' : 'border border-gray-100 bg-white text-gray-500 hover:text-brand')}
                    >
                        {label(link.label)}
                    </Link>
                ) : (
                    <span key={i} className="min-w-9 px-3 py-2 text-center text-[11px] font-black text-gray-300">{label(link.label)}</span>
                ),
            )}
        </nav>
    );
}
