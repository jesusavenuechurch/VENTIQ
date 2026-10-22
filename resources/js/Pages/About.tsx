import { Head } from '@inertiajs/react';
import { Building2, ChevronRight, QrCode, Users } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Reveal } from '@/components/site/reveal';

const eyebrow = 'text-[10px] font-black uppercase tracking-[0.3em] text-action';
const heading = 'mt-4 text-4xl font-black uppercase italic leading-[0.95] tracking-tight text-brand md:text-5xl';
const dots = (color: string, size: number) => ({ backgroundImage: `radial-gradient(${color} 1px, transparent 1px)`, backgroundSize: `${size}px ${size}px` });

/** Why VENTIQ exists and where it's going. */
export default function About() {
    return (
        <SiteLayout>
            <Head title="About" />

            <section className="relative overflow-hidden px-4 pb-24 pt-20 text-center sm:px-6 md:pt-28">
                <div className="pointer-events-none absolute inset-0 opacity-[0.06]" style={dots('#1D4069', 28)} />
                <div className="relative mx-auto max-w-4xl">
                    <span className="inline-flex items-center gap-2 rounded-full bg-mint/60 px-4 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-mint-ink">
                        <span className="size-2 animate-pulse rounded-full bg-mint-ink" />Live in Lesotho
                    </span>
                    <h1 className="mt-8 text-6xl font-black uppercase italic leading-[0.85] tracking-tighter text-brand md:text-8xl">The missing<br /><span className="text-action">middle.</span></h1>
                    <p className="mx-auto mt-8 max-w-2xl text-[16px] leading-relaxed text-gray-500">In Lesotho, informal and formal organizations both fall back on the same manual systems. VENTIQ is the layer in between: registration, tickets, payments and check-in that fit how people here actually run things.</p>
                </div>
            </section>

            <Reveal className="bg-gray-50 px-4 py-24 sm:px-6">
                <div className="mx-auto grid max-w-6xl items-center gap-14 lg:grid-cols-2">
                    <div>
                        <span className={eyebrow}>01 · What we saw</span>
                        <h2 className={heading}>Same problem.<br />Opposite ends.</h2>
                        <p className="mt-6 text-[15px] leading-relaxed text-gray-600">Informal groups are flexible but scattered. Formal institutions are structured but still coordinate by hand. Both end up in the same place: clipboards, WhatsApp groups and spreadsheets.</p>
                        <p className="mt-4 text-[15px] font-bold text-brand">That gap in the middle is where VENTIQ sits.</p>
                    </div>
                    <div className="grid grid-cols-3 items-center gap-3 rounded-[2.5rem] bg-white p-6 shadow-sm">
                        <Column title="Informal" items={['Flexible', 'Manual', 'Scattered', 'No records']} />
                        <div className="flex flex-col items-center gap-3">
                            <span className="flex size-16 items-center justify-center rounded-2xl bg-brand text-lg font-black italic text-white shadow-xl shadow-blue-900/20">VQ</span>
                            <p className="text-[9px] font-black uppercase tracking-widest text-action">Just right</p>
                        </div>
                        <Column title="Formal" items={['Accountable', 'Structured', 'Oversized', 'Complex']} />
                    </div>
                </div>
            </Reveal>

            <Reveal className="px-4 py-24 sm:px-6">
                <div className="mx-auto max-w-6xl">
                    <span className={eyebrow}>02 · The plan</span>
                    <h2 className={heading}>Events first.<br /><span className="text-action">Everything else after.</span></h2>
                    <div className="mt-12 grid gap-5 md:grid-cols-3">
                        {([
                            ['01', 'Event', 'One registration link, one check-in at the door, one clean list of who came.', '#F07F22'],
                            ['02', 'Again', 'The next event starts from the last one. Organizations stop rebuilding from scratch.', '#1D4069'],
                            ['03', 'Every gathering', 'Regular sessions and programmes on the same system: attendance, certificates and reports.', '#1D4069'],
                        ] as const).map(([num, title, text, color]) => (
                            <div key={num} className="relative overflow-hidden rounded-[2rem] border border-gray-100 bg-white p-8 shadow-sm">
                                <div className="absolute inset-x-0 top-0 h-1" style={{ background: color }} />
                                <p className="text-[10px] font-black uppercase tracking-widest" style={{ color }}>Step {num}</p>
                                <h3 className="mt-3 text-xl font-black uppercase text-brand">{title}</h3>
                                <p className="mt-2 text-[13px] leading-relaxed text-gray-500">{text}</p>
                            </div>
                        ))}
                    </div>
                    <div className="relative mt-10 overflow-hidden rounded-[2.5rem] bg-brand p-10 text-center md:p-14">
                        <div className="pointer-events-none absolute inset-0 opacity-10" style={dots('white', 20)} />
                        <p className="relative text-3xl font-black uppercase italic leading-tight text-white md:text-4xl">"Built for here,<br /><span className="text-action">ready for anywhere."</span></p>
                        <p className="relative mx-auto mt-4 max-w-xl text-[13px] text-white/60">What works in Lesotho will work wherever organizations face the same gap.</p>
                    </div>
                </div>
            </Reveal>

            <Reveal className="bg-gray-50 px-4 py-24 sm:px-6">
                <div className="mx-auto max-w-5xl">
                    <span className={eyebrow}>03 · How it fits together</span>
                    <h2 className={heading}>Three parts.<br />One system.</h2>
                    <div className="mt-12 space-y-4">
                        {([
                            [Building2, 'For organizers', 'Create events, set ticket types, see every payment and run your team, from your phone.'],
                            [QrCode, 'For attendees', 'Register in a minute, pay online or by EcoCash, and get a QR ticket by WhatsApp or email.'],
                            [Users, 'Local agents', 'People across the districts bring organizations on board and help them get started.'],
                        ] as [React.ElementType, string, string][]).map(([Icon, title, text], i) => (
                            <div key={title} className="flex items-center gap-6 rounded-[2rem] bg-white p-6 shadow-sm md:p-8">
                                <span className={`flex size-14 shrink-0 items-center justify-center rounded-2xl text-white ${i === 1 ? 'bg-action' : 'bg-brand'}`}><Icon className="size-6" /></span>
                                <div className="flex-1">
                                    <h3 className="text-lg font-black uppercase text-brand">{title}</h3>
                                    <p className="mt-1 text-[13px] leading-relaxed text-gray-500">{text}</p>
                                </div>
                                <ChevronRight className="hidden size-5 text-gray-300 md:block" />
                            </div>
                        ))}
                    </div>
                </div>
            </Reveal>

            <Reveal className="px-4 py-24 sm:px-6">
                <div className="mx-auto max-w-3xl">
                    <span className={eyebrow}>04 · Why we built it</span>
                    <h2 className={heading}>Built in Maseru.<br /><span className="text-action">Made to grow.</span></h2>
                    <div className="mt-8 space-y-4 text-[15px] leading-relaxed text-gray-600">
                        <p>We saw the same clipboard at every event in Lesotho. The same WhatsApp group used as a registration list. The same spreadsheet nobody could find two weeks later.</p>
                        <p>It wasn't a technology problem. It was a structure problem. Organizations weren't short of ambition; they were short of tools that matched their size.</p>
                        <p>So we built VENTIQ. For Maseru first, and for wherever the same gap shows up next.</p>
                    </div>
                    <div className="mt-12 grid grid-cols-2 gap-4 md:grid-cols-4">
                        {['Simple', 'Professional', 'Structured', 'Ready to grow'].map((word) => (
                            <div key={word} className="rounded-2xl border border-gray-100 bg-white p-5 text-center text-[11px] font-black uppercase tracking-widest text-brand shadow-sm">{word}</div>
                        ))}
                    </div>
                </div>
            </Reveal>

            <section className="relative overflow-hidden bg-brand px-4 py-24 text-center sm:px-6">
                <div className="pointer-events-none absolute inset-0 opacity-10" style={dots('white', 24)} />
                <div className="relative mx-auto max-w-3xl">
                    <p className="text-[10px] font-black uppercase tracking-[0.3em] text-action">No monthly fee</p>
                    <h2 className="mt-4 text-5xl font-black uppercase italic leading-[0.9] tracking-tight text-white md:text-6xl">Ready when<br /><span className="text-action">you are.</span></h2>
                    <p className="mx-auto mt-6 max-w-xl text-[14px] text-white/60">Create your first event today. You only pay a small fee on each ticket.</p>
                    <div className="mt-10 flex flex-wrap justify-center gap-3">
                        <a href={route('org.register.direct')} className="rounded-2xl bg-action px-8 py-4 text-[11px] font-black uppercase tracking-[0.2em] text-white transition-all hover:bg-white hover:text-brand">Create an account</a>
                        <a href={route('events.browse')} className="rounded-2xl border border-white/20 px-8 py-4 text-[11px] font-black uppercase tracking-[0.2em] text-white transition-all hover:border-white">Browse events</a>
                    </div>
                </div>
            </section>
        </SiteLayout>
    );
}

function Column({ title, items }: { title: string; items: string[] }) {
    return (
        <div className="space-y-2">
            <p className="text-center text-[9px] font-black uppercase tracking-widest text-gray-400">{title}</p>
            {items.map((item) => <div key={item} className="rounded-xl bg-gray-50 px-2 py-2 text-center text-[10px] font-bold text-gray-500">{item}</div>)}
        </div>
    );
}
