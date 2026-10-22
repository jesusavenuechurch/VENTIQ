import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, CalendarCheck, CalendarPlus, ChevronLeft, ChevronRight, Compass, Images, LogIn, Mic } from 'lucide-react';
import { useEffect, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Reveal } from '@/components/site/reveal';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Upcoming {
    name: string;
    venue: string | null;
    city: string | null;
    category_label: string | null;
    category_color: string | null;
    organizer: string | null;
    date: string | null;
    time: string | null;
    image: string | null;
    url: string;
}

interface Collection {
    key: string;
    label: string;
    color: string;
    count: number;
    events: string[];
    remaining: number;
    image: string | null;
    href: string;
}

interface Props {
    upcoming: Upcoming[];
    metrics: { total_events: number; total_cities: number; total_categories: number };
    month: string;
    links: { sessions: string; host: string };
}

/** Follows the district picked in the header (SiteLayout's "district-changed"). */
function useHeaderDistrict(): string {
    const [district, setDistrict] = useState('Maseru');
    useEffect(() => {
        const on = (e: Event) => setDistrict((e as CustomEvent<string>).detail);
        window.addEventListener('district-changed', on);
        return () => window.removeEventListener('district-changed', on);
    }, []);
    return district;
}

/** An image that fades in over a shimmer once it has loaded. */
function Photo({ src, srcSet, sizes, alt = '', className }: { src: string; srcSet?: string; sizes?: string; alt?: string; className?: string }) {
    const [loaded, setLoaded] = useState(false);
    return (
        <>
            {!loaded && <div className="absolute inset-0 animate-pulse bg-slate-100" />}
            <picture className="contents">
                {srcSet && <source type="image/webp" sizes={sizes} srcSet={srcSet} />}
                <img src={src} alt={alt} loading="lazy" decoding="async" onLoad={() => setLoaded(true)} className={cn('relative size-full object-cover transition-opacity duration-500', loaded ? 'opacity-100' : 'opacity-0', className)} />
            </picture>
        </>
    );
}

const hero = (name: string) => ({
    src: `/images/hero/web/${name}-1280.jpg`,
    srcSet: [640, 1280, 1920].map((w) => `/images/hero/web/${name}-${w}.webp ${w}w`).join(', '),
});

/** VENTIQ's front door: what's on near you, and a way in for organizers. */
export default function Home({ upcoming: initial, metrics, month, links }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const district = useHeaderDistrict();
    const [upcoming, setUpcoming] = useState(initial);
    const [loadingUpcoming, setLoadingUpcoming] = useState(false);
    const [collections, setCollections] = useState<Collection[] | null>(null);
    const [active, setActive] = useState(0);

    // Both lists follow the district.
    useEffect(() => {
        const q = new URLSearchParams({ district }).toString();
        setLoadingUpcoming(true);
        fetch(`${route('events.upcoming')}?${q}`).then((r) => r.json()).then(setUpcoming).catch(() => undefined).finally(() => setLoadingUpcoming(false));
        fetch(`${route('events.discover')}?${q}`).then((r) => r.json()).then((c: Collection[]) => { setCollections(c); setActive(0); }).catch(() => setCollections([]));
    }, [district]);

    // The carousel moves on every 5 seconds; a tap restarts the clock.
    useEffect(() => {
        if (!collections || collections.length < 2) return;
        const t = window.setInterval(() => setActive((a) => (a + 1) % collections.length), 5000);
        return () => window.clearInterval(t);
    }, [collections, active]);

    const n = collections?.length ?? 0;
    const offset = (i: number) => {
        let d = i - active;
        if (d > n / 2) d -= n;
        if (d < -n / 2) d += n;
        return d;
    };

    const quick = [
        { href: links.sessions, icon: Mic, tone: 'bg-brand', title: 'Sessions', hint: 'Capture notes, live' },
        { href: links.host, icon: CalendarPlus, tone: 'bg-action', title: 'Host an event', hint: 'List on Ventiq' },
        { href: route('public.session-join'), icon: LogIn, tone: 'bg-gray-800', title: 'Join a session', hint: 'Enter a session code' },
    ];
    const chips: [string, string][] = [['Music', 'rotate-3 bg-rose-50 text-rose-700 border-rose-100/60'], ['Markets', '-rotate-3 bg-amber-50 text-amber-800 border-amber-100/60'], ['Sports', 'rotate-6 bg-emerald-50 text-emerald-700 border-emerald-100/60'], ['Worship', '-rotate-2 bg-indigo-50 text-indigo-700 border-indigo-100/40'], ['Business', 'rotate-2 bg-sky-50 text-sky-700 border-sky-100/60'], ['Arts', '-rotate-3 bg-purple-50 text-purple-700 border-purple-100/50']];

    return (
        <SiteLayout>
            <Head title="Discover what's happening" />
            <div className="w-full bg-white font-sans text-gray-900 selection:bg-action/10 selection:text-action">
                {/* Hero */}
                <section className="relative flex min-h-[calc(100vh-5rem)] w-full flex-col items-center justify-center overflow-hidden px-6 pb-12 pt-16">
                    <div className="grid w-full max-w-7xl flex-1 grid-cols-1 items-center gap-8 lg:grid-cols-12 lg:gap-4">
                        <div className="order-2 mt-8 flex flex-col lg:order-none lg:col-span-3 lg:mt-0 lg:h-[450px]">
                            <div className="flex h-full w-full flex-col overflow-hidden rounded-[2rem] border border-gray-100 bg-[#F8FAFC]">
                                <div className="px-5 pb-4 pt-6">
                                    <p className="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400">Not here for events?</p>
                                    <p className="mt-1 text-[13px] font-black uppercase tracking-tight text-brand">Quick access</p>
                                </div>
                                <div className="flex-1 space-y-1 px-3 pb-4">
                                    {quick.map((q) => (
                                        <a key={q.title} href={q.href} className="flex items-center gap-3 rounded-2xl px-2.5 py-3 transition-all hover:bg-white hover:shadow-sm">
                                            <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full text-white', q.tone)}><q.icon className="size-3.5" /></span>
                                            <span className="min-w-0">
                                                <span className="block text-[11.5px] font-black uppercase tracking-tight text-brand">{q.title}</span>
                                                <span className="block truncate text-[9px] font-bold text-gray-400">{q.hint}</span>
                                            </span>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="z-10 order-1 col-span-1 flex flex-col items-center justify-center px-4 lg:order-none lg:col-span-6">
                            <h1 className="mb-6 text-center text-4xl font-black uppercase leading-[1.1] tracking-tight text-gray-900 md:text-5xl">Discover<br />What's<br />Happening<span className="text-action">.</span></h1>
                            <p className="mb-12 max-w-sm text-center text-sm font-medium italic tracking-tight text-gray-500 md:text-base">Some moments deserve more than a calendar reminder.</p>
                            <Link href={route('events.browse')} className="mb-16 inline-flex items-center justify-center gap-2.5 rounded-full bg-brand px-10 py-4 text-[11px] font-bold uppercase tracking-[0.25em] text-white shadow-xl shadow-blue-900/5 transition-all duration-300 hover:bg-action active:scale-[0.98]">
                                <Compass className="size-3.5" />Explore events
                            </Link>
                            <div className="mx-auto flex w-full max-w-md select-none flex-wrap items-center justify-center gap-3 px-4">
                                {chips.map(([label, tone]) => (
                                    <Link key={label} href={route('events.browse')} className={cn('rounded-2xl border px-5 py-2 text-[10px] font-black uppercase tracking-widest shadow-sm transition-all duration-200 hover:rotate-0 hover:scale-105', tone)}>{label}</Link>
                                ))}
                            </div>
                        </div>

                        <div className="pointer-events-none relative hidden h-[450px] select-none flex-col items-center justify-center lg:col-span-3 lg:flex" aria-hidden>
                            <div className="absolute h-64 w-52 -translate-x-4 -translate-y-4 rotate-6 overflow-hidden rounded-[2.25rem] border border-gray-100 bg-gray-100 opacity-70 shadow-xl"><Photo {...hero('3')} sizes="208px" /></div>
                            <div className="absolute h-64 w-52 translate-x-4 translate-y-8 -rotate-3 overflow-hidden rounded-[2.25rem] border border-white/80 bg-gray-100 shadow-2xl shadow-gray-200/60"><Photo {...hero('4')} sizes="208px" /></div>
                        </div>
                    </div>
                    <div className="pointer-events-none mt-8 animate-bounce select-none text-base font-light tracking-widest text-gray-300" aria-hidden>↓</div>
                </section>

                {/* Discover: collections for the district */}
                {(collections === null || collections.length > 0) && (
                    <Reveal className="w-full overflow-hidden px-6 py-20">
                        <div className="mx-auto mb-10 flex max-w-5xl items-end justify-between px-2">
                            <div>
                                <span className="mb-1 block text-[10px] font-black uppercase tracking-[0.3em] text-gray-400">Right now</span>
                                <h2 className="text-2xl font-black uppercase tracking-tight text-gray-950 md:text-3xl">Discover<span className="text-action">.</span></h2>
                            </div>
                            {n > 0 && <div className="text-xs font-black tabular-nums text-gray-300">{String(active + 1).padStart(2, '0')}/{String(n).padStart(2, '0')}</div>}
                        </div>
                        <div className="relative mx-auto h-72 max-w-5xl md:h-80">
                            {(collections ?? []).map((col, i) => {
                                const o = offset(i);
                                const far = Math.abs(o);
                                return (
                                    <Link
                                        key={col.key}
                                        href={col.href}
                                        tabIndex={o === 0 ? 0 : -1}
                                        className="absolute left-1/2 top-0 h-full w-80 overflow-hidden rounded-[1.75rem] shadow-2xl transition-all duration-500 ease-out md:w-[26rem]"
                                        style={{ transform: `translateX(calc(-50% + ${o * 56}%)) scale(${o === 0 ? 1 : far === 1 ? 0.78 : 0.6})`, zIndex: 10 - far, opacity: far > 2 ? 0 : o === 0 ? 1 : far === 1 ? 0.55 : 0.3, pointerEvents: far > 2 ? 'none' : 'auto' }}
                                    >
                                        {col.image ? <Photo src={col.image} /> : <div className="absolute inset-0" style={{ background: `linear-gradient(160deg, ${col.color}, ${col.color}cc)` }} />}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent" />
                                        {o === 0 && <span className="absolute right-4 top-4 flex size-8 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm"><ArrowUpRight className="size-3.5 text-white" /></span>}
                                        <div className="absolute inset-0 flex flex-col justify-end p-6">
                                            <p className={cn('whitespace-pre-line font-black uppercase leading-[0.95] tracking-tight text-white drop-shadow-sm transition-all duration-300', o === 0 ? 'mb-3 text-2xl md:text-3xl' : 'text-base md:text-lg')}>{col.label}</p>
                                            {o === 0 && (
                                                <div>
                                                    <p className="mb-2.5 text-[11px] font-bold uppercase tracking-widest text-white/60">{col.count} {col.count === 1 ? 'event' : 'events'}</p>
                                                    <ul className="mb-1 space-y-1">{col.events.map((name) => <li key={name} className="truncate text-[12px] font-semibold text-white/85">{name}</li>)}</ul>
                                                    {col.remaining > 0 && <p className="mt-1 text-[11px] font-bold uppercase tracking-wide text-white/50">+{col.remaining} more</p>}
                                                </div>
                                            )}
                                        </div>
                                    </Link>
                                );
                            })}
                            {n > 1 && (
                                <>
                                    <button type="button" aria-label="Previous" onClick={() => setActive((active - 1 + n) % n)} className="absolute left-0 top-1/2 z-20 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-brand shadow-md transition-colors hover:bg-brand hover:text-white md:-left-8"><ChevronLeft className="size-4" /></button>
                                    <button type="button" aria-label="Next" onClick={() => setActive((active + 1) % n)} className="absolute right-0 top-1/2 z-20 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-brand shadow-md transition-colors hover:bg-brand hover:text-white md:-right-8"><ChevronRight className="size-4" /></button>
                                </>
                            )}
                        </div>
                        {n > 1 && (
                            <div className="mt-8 flex justify-center gap-2">
                                {collections!.map((col, i) => <button key={col.key} type="button" aria-label={`Show ${col.label}`} onClick={() => setActive(i)} className={cn('h-1.5 rounded-full transition-all duration-300', active === i ? 'w-6 bg-brand' : 'w-1.5 bg-gray-200')} />)}
                            </div>
                        )}
                    </Reveal>
                )}

                {/* Upcoming in the district */}
                <Reveal className="mx-auto w-full max-w-7xl border-t border-gray-100 px-6 py-16">
                    <div className="mb-12 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                        <div>
                            <span className="mb-1 text-[10px] font-black uppercase tracking-[0.3em] text-gray-400">Live feed</span>
                            <h2 className="text-2xl font-black uppercase tracking-tight text-gray-950 md:text-3xl">Happening in {district}<span className="text-action">.</span></h2>
                        </div>
                        <Link href={route('events.browse', { district })} className="group inline-flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-brand transition-colors hover:text-action">
                            Explore all events <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </div>
                    <div className={cn('grid grid-cols-2 gap-4 transition-opacity md:grid-cols-4 md:gap-5', loadingUpcoming && 'pointer-events-none opacity-50')}>
                        {upcoming.length === 0 && !loadingUpcoming && (
                            <div className="col-span-full rounded-[2rem] border border-dashed border-gray-200 py-16 text-center"><p className="text-sm font-medium italic text-gray-400">Nothing scheduled here right now. Check back soon.</p></div>
                        )}
                        {upcoming.map((e) => (
                            <Link key={e.url + e.name} href={e.url} className="group block overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:shadow-lg">
                                <div className="relative aspect-[4/3] w-full overflow-hidden bg-gray-50">
                                    {e.image ? <Photo src={e.image} alt="" className="transition-transform duration-500 group-hover:scale-[1.02]" /> : (
                                        <div className="flex size-full items-center justify-center overflow-hidden bg-brand"><span className="-rotate-6 select-none text-[32px] font-black uppercase leading-none tracking-tight text-white/10">VENTIQ</span></div>
                                    )}
                                    {e.category_label && <span className="absolute left-2.5 top-2.5 rounded-full px-2.5 py-1 text-[8px] font-black uppercase tracking-wider text-white shadow-sm" style={{ backgroundColor: e.category_color || '#1D4069' }}>{e.category_label}</span>}
                                </div>
                                <div className="p-3.5">
                                    <div className="mb-1.5 flex items-center gap-1.5 text-[9px] font-bold uppercase tracking-wider text-gray-400">
                                        <span className="text-action">{e.date || 'TBA'}</span>{e.time && <><span>•</span><span>{e.time}</span></>}
                                    </div>
                                    <h3 className="mb-1.5 line-clamp-2 text-[13px] font-black uppercase leading-snug tracking-tight text-gray-900 transition-colors group-hover:text-brand">{e.name}</h3>
                                    {(e.venue || e.city) && <p className="truncate text-[10px] font-medium text-gray-400">{[e.venue, e.city].filter(Boolean).join(' · ')}</p>}
                                    {e.organizer && <p className="mt-1 truncate text-[9px] font-bold uppercase tracking-wide text-gray-400">By <span className="text-gray-500">{e.organizer}</span></p>}
                                </div>
                            </Link>
                        ))}
                    </div>
                </Reveal>

                {/* This month in numbers */}
                <Reveal className="my-8 w-full border-y border-gray-100 bg-gray-50/60 py-12">
                    <div className="mx-auto grid w-full max-w-7xl grid-cols-2 gap-8 px-6 text-center md:grid-cols-4 md:text-left">
                        {([[month, 'Current rhythm', 'text-gray-900 uppercase tracking-tight'], [metrics.total_events, 'Live gatherings', 'text-brand'], [metrics.total_cities, 'Active districts', 'text-action'], [metrics.total_categories, 'Kinds of event', 'text-emerald-700']] as const).map(([value, label, tone]) => (
                            <div key={label} className="flex flex-col items-center justify-center md:items-start">
                                <span className={cn('text-2xl font-black', tone)}>{value}</span>
                                <span className="mt-0.5 text-[10px] font-bold uppercase tracking-widest text-gray-400">{label}</span>
                            </div>
                        ))}
                    </div>
                </Reveal>

                {/* How it works */}
                <Reveal className="relative w-full overflow-hidden bg-brand px-6 py-24 text-white">
                    <div className="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-action opacity-10 blur-3xl" aria-hidden />
                    <div className="relative z-10 mx-auto w-full max-w-5xl">
                        <div className="mb-20 flex flex-col items-center text-center">
                            <span className="mb-2 text-[10px] font-black uppercase tracking-[0.3em] text-action">The flow</span>
                            <h2 className="text-2xl font-black uppercase tracking-tight md:text-3xl">How VENTIQ works<span className="text-action">.</span></h2>
                        </div>
                        <div className="flex flex-col items-center justify-center gap-10 md:flex-row md:gap-12">
                            {([[Compass, 'Find an event', '-rotate-6 bg-brand border-l-4 border-[#0F2A47] h-52', 'text-action'], [CalendarCheck, 'Reserve your space', 'rotate-3 -translate-y-3 bg-action border-l-4 border-[#B85C12] h-60', 'text-white'], [Images, 'Keep the memory', 'rotate-6 bg-white/10 border border-white/15 border-l-4 border-l-white/25 h-52', 'text-action']] as const).map(([Icon, text, tone, iconTone]) => (
                                <div key={text} className={cn('relative flex w-40 shrink-0 flex-col justify-end rounded-l-sm rounded-r-lg p-4 shadow-xl', tone)}>
                                    <Icon className={cn('mb-3 size-5', iconTone)} />
                                    <p className="text-xs font-black uppercase leading-snug tracking-widest">{text}</p>
                                </div>
                            ))}
                        </div>
                        <div className="mt-20 flex justify-center">
                            <Link href={route('about')} className="rounded-full border border-white/20 px-8 py-3 text-[10px] font-bold uppercase tracking-widest transition-all hover:border-white">Our philosophy</Link>
                        </div>
                    </div>
                </Reveal>

                {/* Community */}
                <Reveal className="w-full overflow-hidden bg-white py-20">
                    <div className="mx-auto mb-12 flex w-full max-w-7xl flex-col items-center px-6 text-center">
                        <span className="mb-1 text-[10px] font-black uppercase tracking-[0.3em] text-gray-400">Coming soon</span>
                        <h2 className="text-2xl font-black uppercase tracking-tight text-gray-950">Moments from the community<span className="text-action">.</span></h2>
                    </div>
                    <div className="pointer-events-none mx-auto grid w-full max-w-7xl select-none grid-cols-2 gap-4 px-6 md:grid-cols-4 md:gap-6" aria-hidden>
                        {([['3', 'rotate-2'], ['4', '-rotate-3 translate-y-4'], ['sing', 'rotate-1'], ['2', '-rotate-2 translate-y-2']] as const).map(([name, tilt]) => (
                            <div key={name} className={cn('relative aspect-[3/4] overflow-hidden rounded-[2rem] border border-gray-100 bg-gray-50 shadow-sm', tilt)}>
                                <Photo {...hero(name)} sizes="(min-width: 768px) 22vw, 45vw" />
                            </div>
                        ))}
                    </div>
                </Reveal>

                {/* For organizers */}
                <Reveal className="mx-auto mb-8 w-full max-w-5xl border-t border-gray-100 px-6 py-16">
                    <div className="flex flex-col items-center justify-between gap-8 rounded-[2.5rem] border border-gray-200/60 bg-gray-50 p-8 md:flex-row md:p-12">
                        <div className="max-w-md text-center md:text-left">
                            <h3 className="mb-2 text-lg font-black uppercase tracking-tight text-gray-950">Running an event?</h3>
                            <p className="text-xs font-medium leading-relaxed tracking-tight text-gray-500 md:text-sm">Registrations, tickets, payments, check-in at the door and reports, in one place. No monthly fee.</p>
                        </div>
                        <a href={auth.user ? links.host : route('pricing')} className="whitespace-nowrap rounded-full bg-brand px-8 py-3.5 text-[10px] font-bold uppercase tracking-widest text-white shadow-md transition-all duration-300 hover:bg-action active:scale-[0.98]">
                            {auth.user ? 'Go to my events' : 'See pricing'}
                        </a>
                    </div>
                </Reveal>
            </div>
        </SiteLayout>
    );
}
