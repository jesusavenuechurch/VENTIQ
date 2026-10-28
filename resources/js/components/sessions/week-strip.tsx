import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface WeekDay {
    date: string;
    weekday: string;
    day: string;
    label: string;
    sessions: { id: number; title: string; status: string; time: string | null; tracking: boolean }[];
    actions: { id: number; task: string; owner: string | null; dueLabel: string | null; overdue: boolean; session: { id: number; title: string } | null }[];
}

export interface Week {
    start: string;
    label: string;
    prev: string;
    next: string;
    days: WeekDay[];
}

/** Monday to Sunday, a dot per session (a ring for action points due); tap a day to see it. */
export function WeekStrip({ week, today, selected, onSelect }: { week: Week; today: string; selected: string | null; onSelect: (date: string | null) => void }) {
    const go = (to: string) => {
        onSelect(null);
        router.get(route('sessions.index'), { week: to }, { only: ['week'], preserveState: true, preserveScroll: true, replace: true });
    };
    const arrow = 'flex size-8 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-brand';

    return (
        <div className="flex min-w-0 flex-1 items-center gap-1 rounded-2xl border border-gray-100 bg-white p-1.5 shadow-sm">
            <button type="button" onClick={() => go(week.prev)} className={arrow} aria-label="The week before"><ChevronLeft className="size-4" /></button>
            <div className="grid min-w-0 flex-1 grid-cols-7 gap-0.5" title={week.label}>
                {week.days.map((d) => {
                    const isToday = d.date === today;
                    const isSelected = d.date === selected;
                    const dots = Math.min(d.sessions.length, 3);
                    return (
                        <button
                            key={d.date}
                            type="button"
                            onClick={() => onSelect(isSelected ? null : d.date)}
                            aria-pressed={isSelected}
                            aria-label={`${d.label}: ${d.sessions.length} ${d.sessions.length === 1 ? 'session' : 'sessions'}${d.actions.length ? `, ${d.actions.length} due` : ''}`}
                            className={cn(
                                'flex flex-col items-center rounded-xl py-1.5 transition-colors',
                                isSelected ? 'bg-brand text-white' : isToday ? 'bg-action/10 text-action' : 'text-brand hover:bg-gray-50',
                            )}
                        >
                            <span className={cn('text-[9px] font-black uppercase tracking-widest', isSelected ? 'text-white/70' : 'text-gray-400', isToday && !isSelected && 'text-action')}>{d.weekday}</span>
                            <span className="text-[14px] font-black leading-tight">{d.day}</span>
                            <span className="flex h-1.5 items-center gap-0.5">
                                {Array.from({ length: dots }).map((_, i) => <span key={i} className={cn('size-1.5 rounded-full', isSelected ? 'bg-white' : 'bg-action')} />)}
                                {d.actions.length > 0 && <span className={cn('size-1.5 rounded-full border', isSelected ? 'border-white' : 'border-emerald-600')} />}
                            </span>
                        </button>
                    );
                })}
            </div>
            <button type="button" onClick={() => go(week.next)} className={arrow} aria-label="The week after"><ChevronRight className="size-4" /></button>
        </div>
    );
}
