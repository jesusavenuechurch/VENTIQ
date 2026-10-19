import { Link } from '@inertiajs/react';
import { ChevronRight, Lock, Plus } from 'lucide-react';
import SessionsLayout, { primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { AppLink } from '@/components/app-link';

interface Programme {
    id: number;
    name: string;
    dates: string | null;
    sessions: number;
}

/** Programmes: anything with more than one session, like a training over several days. */
export default function Index({ programmes, canCreate }: { programmes: Programme[]; canCreate: boolean }) {
    return (
        <SessionsLayout
            title="Programmes"
            subtitle="For anything with more than one session: trainings, conferences, events over several days."
            width="max-w-4xl"
            actions={
                canCreate ? (
                    <AppLink to="programmes.create" className={primaryButton}><Plus className="size-4" />New programme</AppLink>
                ) : (
                    <AppLink to="sessions.plan" className={quietButton}><Lock className="size-3.5" />Business plan</AppLink>
                )
            }
        >
            {!canCreate && (
                <p className="mb-6 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-[12px] font-bold text-amber-800">Programmes come with the Business plan and above.</p>
            )}
            <div className="space-y-3">
                {programmes.map((p) => (
                    <Link key={p.id} href={route('programmes.show', p.id)} className="flex items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white px-5 py-4 transition-all hover:border-brand/30 hover:shadow-sm">
                        <div className="min-w-0">
                            <p className="truncate text-[13px] font-bold text-brand">{p.name}</p>
                            {p.dates && <p className="mt-0.5 text-[11px] font-bold text-gray-400">{p.dates}</p>}
                        </div>
                        <span className="flex shrink-0 items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            {p.sessions} {p.sessions === 1 ? 'session' : 'sessions'}<ChevronRight className="size-4 text-gray-300" />
                        </span>
                    </Link>
                ))}
                {programmes.length === 0 && (
                    <p className="py-16 text-center text-[12px] italic text-gray-400">No programmes yet. Use one when a single session isn't enough.</p>
                )}
            </div>
        </SessionsLayout>
    );
}
