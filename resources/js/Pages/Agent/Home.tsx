import { Head, Link, usePage } from '@inertiajs/react';
import { BadgeCheck, Copy, GraduationCap, MessageCircle, Sparkles } from 'lucide-react';
import { useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Alert } from '@/components/ui/alert';
import { cn, money } from '@/lib/utils';

interface Props {
    agent: { name: string; referralUrl: string; status: string };
    certified: boolean;
    cert: { score: number; total: number; percent: number; attempts: number } | null;
    stats: { organizations: number; active: number; paid: number; owed: number };
    next: string;
    organizations: { id: number; name: string; joined: string | null; events: number; state: 'active' | 'registered'; whatsapp: string | null }[];
    earnings: { id: number; type: string; organization: string | null; amount: number; status: string; date: string | null }[];
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm';

/** A sales agent's home: who they've brought in, what they've earned, their link. */
export default function AgentHome({ agent, certified, stats, next, organizations, earnings }: Props) {
    const { flash } = usePage().props;
    const [copied, setCopied] = useState(false);
    const first = agent.name.split(' ')[0];

    function copy() {
        void navigator.clipboard?.writeText(agent.referralUrl).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    }

    return (
        <SiteLayout>
            <Head title="Agent" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-8">
                <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Ventiq · Sales agent</p>
                    <h1 className="mt-1 text-2xl font-black tracking-tight text-brand">Lumela, {first}!</h1>
                </div>
                {flash.status && <Alert className="rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}

                <div className={cn('flex flex-wrap items-center justify-between gap-4 rounded-[1.5rem] p-5', certified ? 'bg-lilac text-lilac-ink' : 'bg-action text-white')}>
                    <p className="text-[13px] font-bold"><Sparkles className="mr-2 inline size-4" />{next}</p>
                    {!certified && (
                        <Link href={route('agent.certification')} className="rounded-full bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-action-ink">
                            <GraduationCap className="mr-1 inline size-3.5" />Get certified
                        </Link>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {([['Organizations', String(stats.organizations), 'text-brand'], ['Running events', String(stats.active), 'text-mint-ink'], ['Owed to you', money(stats.owed), 'text-action-ink'], ['Paid to you', money(stats.paid), 'text-brand']] as const).map(([label, value, tone]) => (
                        <div key={label} className={card}>
                            <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{label}</p>
                            <p className={cn('mt-1 text-2xl font-black', tone)}>{value}</p>
                        </div>
                    ))}
                </div>

                <div className={card}>
                    <p className="mb-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Your referral link {certified && <BadgeCheck className="ml-1 inline size-3.5 text-mint-ink" />}</p>
                    <div className="flex flex-wrap items-center gap-2">
                        <code className="min-w-0 flex-1 truncate rounded-2xl bg-slate-50 px-4 py-3 font-mono text-[13px] text-brand">{agent.referralUrl}</code>
                        <button type="button" onClick={copy} className="rounded-2xl bg-brand px-4 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action"><Copy className="mr-1 inline size-3.5" />{copied ? 'Copied' : 'Copy'}</button>
                    </div>
                    <p className="mt-2 text-[11px] text-gray-500">Organizations that register through this link are yours.</p>
                </div>

                <div className="grid gap-6 lg:grid-cols-5">
                    <div className={cn(card, 'lg:col-span-3')}>
                        <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Your organizations</p>
                        {organizations.length === 0 ? <p className="text-[12px] text-gray-400">Nobody yet. Share your link to bring in your first.</p> : (
                            <ul className="divide-y divide-gray-50">
                                {organizations.map((o) => (
                                    <li key={o.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                                        <div className="min-w-0">
                                            <p className="text-[14px] font-black text-brand">{o.name}</p>
                                            <p className="text-[11px] text-gray-400">Joined {o.joined} · {o.state === 'active' ? `${o.events} ${o.events === 1 ? 'event' : 'events'}` : 'No event yet'}</p>
                                        </div>
                                        {o.whatsapp && <a href={o.whatsapp} target="_blank" rel="noopener" className="inline-flex items-center gap-1 rounded-full bg-[#25D366] px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white"><MessageCircle className="size-3" />Check in</a>}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <div className={cn(card, 'lg:col-span-2')}>
                        <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Earnings</p>
                        {earnings.length === 0 ? <p className="text-[12px] text-gray-400">Nothing yet.</p> : (
                            <ul className="divide-y divide-gray-50 text-[12px]">
                                {earnings.map((e) => (
                                    <li key={e.id} className="flex items-center justify-between gap-2 py-2">
                                        <span className="min-w-0"><span className="font-bold text-brand">{e.type}</span>{e.organization && ` · ${e.organization}`}<span className="block text-[11px] text-gray-400">{e.date}</span></span>
                                        <span className="text-right"><span className="block font-black">{money(e.amount)}</span><span className={cn('text-[10px] font-black uppercase tracking-widest', e.status === 'paid' ? 'text-mint-ink' : 'text-action-ink')}>{e.status === 'paid' ? 'Paid' : 'Owed'}</span></span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}
