import { router } from '@inertiajs/react';
import { useState } from 'react';
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

/**
 * Monday to Sunday, small and to one side: a dot per session (a ring for
 * action points due). Tap a day to see it; today, or tapping it again,
 * goes back to the desk.
 */
export function WeekStrip({ week, today, selected, onSelect }: { week: Week; today: string; selected: string | null; onSelect: (date: string | null) => void }) {
    const [direction, setDirection] = useState<'prev' | 'next' | null>(null);
    const thisWeek = week.days.some((d) => d.date === today);
    const go = (to: string, way: 'prev' | 'next') => {
        onSelect(null);
        setDirection(way);
        router.get(route('sessions.index'), { week: to }, { only: ['week'], preserveState: true, preserveScroll: true, replace: true });
    };
    const arrow = 'flex size-7 shrink-0 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-brand';

    return (
        <div className="inline-flex max-w-full items-center gap-0.5 self-start sm:self-auto rounded-2xl border border-gray-100 bg-white p-1 shadow-sm">
            <button type="button" onClick={() => go(week.prev, 'prev')} className={arrow} aria-label="The week before"><ChevronLeft className="size-4" /></button>
            <div className="overflow-hidden">
                <div
                    key={week.start}
                    title={week.label}
                    className={cn(
                        'flex gap-0.5 duration-300 ease-out',
                        direction && 'animate-in fade-in',
                        direction === 'next' && 'slide-in-from-right-6',
                        direction === 'prev' && 'slide-in-from-left-6',
                    )}
                >
                    {week.days.map((d) => {
                        const isToday = d.date === today;
                        const isSelected = d.date === selected;
                        const dots = Math.min(d.sessions.length, 3);
                        return (
                            <button
                                key={d.date}
                                type="button"
                                onClick={() => onSelect(isSelected || isToday ? null : d.date)}
                                aria-pressed={isSelected}
                                aria-label={`${d.label}: ${d.sessions.length} ${d.sessions.length === 1 ? 'session' : 'sessions'}${d.actions.length ? `, ${d.actions.length} due` : ''}`}
                                className={cn(
                                    'flex w-9 flex-col items-center rounded-xl py-1 transition-colors',
                                    isSelected ? 'bg-brand text-white' : isToday ? 'bg-action/10 text-action' : 'text-brand hover:bg-gray-50',
                                )}
                            >
                                <span className={cn('text-[8px] font-black uppercase tracking-wider', isSelected ? 'text-white/70' : isToday ? 'text-action' : 'text-gray-400')}>{d.weekday.slice(0, 2)}</span>
                                <span className="text-[12px] font-black leading-tight">{d.day}</span>
                                <span className="flex h-1 items-center gap-0.5">
                                    {Array.from({ length: dots }).map((_, i) => <span key={i} className={cn('size-1 rounded-full', isSelected ? 'bg-white' : 'bg-action')} />)}
                                    {d.actions.length > 0 && <span className={cn('size-1 rounded-full ring-1', isSelected ? 'ring-white' : 'ring-emerald-600')} />}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>
            <button type="button" onClick={() => go(week.next, 'next')} className={arrow} aria-label="The week after"><ChevronRight className="size-4" /></button>
            {!thisWeek && (
                <button type="button" onClick={() => go(today, week.start > today ? 'prev' : 'next')} className="ml-0.5 shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-action hover:bg-action/10">Today</button>
            )}
        </div>
    );
}
