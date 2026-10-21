import { cn } from '@/lib/utils';

interface Count { label: string; count: number }

export interface SessionNumbersData {
    attendance: {
        checkedIn: number; expected: number | null; rate: number | null; noShows: number; signed: number; late: number | null;
        how: Count[]; from: Count[]; districts: Count[]; arrivals: (Count & { late: boolean })[];
    } | null;
    time: { items: { label: string; lead: string | null; minutes: number }[]; total: number; planned: number | null; started: string | null; ended: string | null };
    notes: { lines: number; items: number; of: number };
    outcomes: {
        decisions: number;
        agreed: { total: number; proposed: number; done: number; overdue: number };
        carried: { total: number; doneHere: number; open: number; overdue: number };
        owners: Count[];
    };
    programme: { name: string; day: number; days: (Count & { date: string | null; this: boolean })[]; enrolled: number; required: number | null; certificates: number | null } | null;
}

const panel = 'rounded-2xl border border-gray-100 bg-white p-6';
const caption = 'mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400';

/** "1h 20m", "45m". */
export function minutesLabel(m: number) {
    return m >= 60 ? `${Math.floor(m / 60)}h${m % 60 ? ` ${m % 60}m` : ''}` : `${m}m`;
}

/**
 * The report's Summary and numbers: what was said, in a line an item,
 * and the session in figures. Bars are one hue; a number is written on
 * each, so nothing is read from colour alone.
 */
export function SessionNumbers({ numbers, summary, minuted }: { numbers: SessionNumbersData; summary: { label: string; text: string | null }[]; minuted: boolean }) {
    const { attendance: a, time, outcomes: o, programme: p } = numbers;
    const over = time.planned ? time.total - time.planned : null;
    const written = summary.filter((s) => s.text);

    return (
        <div className="space-y-6">
            {/* The headline figures. */}
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {a ? (
                    <Tile label="Checked in" value={a.checkedIn} detail={a.expected ? `of ${a.expected} expected · ${a.rate}%` : 'No number expected was set'} />
                ) : (
                    <Tile label={minuted ? 'Items taken' : 'Presenters'} value={time.items.length} detail={`${numbers.notes.lines} lines of notes`} />
                )}
                <Tile label="Time" value={minutesLabel(time.total)} detail={time.planned ? (over === 0 ? 'As planned' : `${minutesLabel(Math.abs(over!))} ${over! > 0 ? 'over' : 'under'} the ${minutesLabel(time.planned)} planned`) : (time.started ? `${time.started} to ${time.ended}` : '')} />
                <Tile label="Decisions" value={o.decisions} detail={o.decisions ? (minuted ? 'In the minutes' : 'In the report') : 'None recorded'} />
                <Tile label="Action points" value={o.agreed.total} detail={o.agreed.proposed ? `${o.agreed.proposed} waiting to be confirmed` : o.agreed.total ? `${o.agreed.done} done since` : 'None agreed'} />
            </div>

            {written.length > 0 && (
                <div className={panel}>
                    <p className={caption}>In short</p>
                    <ol className="space-y-3">
                        {written.map((s, i) => (
                            <li key={i} className="text-[13px] leading-relaxed text-gray-700"><span className="font-bold text-brand">{s.label}.</span> {firstSentences(s.text!)}</li>
                        ))}
                    </ol>
                </div>
            )}

            {a && (
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className={panel}>
                        <p className={caption}>How people checked in</p>
                        <Bars rows={a.how} total={a.checkedIn} />
                        <p className="mt-4 text-[11px] font-medium text-gray-400">
                            {a.signed} signed on the scanner{a.noShows > 0 ? ` · ${a.noShows} registered but didn't come` : ''}
                        </p>
                    </div>
                    <div className={panel}>
                        <p className={caption}>When they arrived</p>
                        {a.arrivals.length ? <Arrivals rows={a.arrivals} /> : <Nothing />}
                        {a.late !== null && <p className="mt-4 text-[11px] font-medium text-gray-400">{a.late === 0 ? 'Everyone was in within 15 minutes of the start.' : `${a.late} arrived 15 minutes or more after the start.`}</p>}
                    </div>
                    <div className={panel}>
                        <p className={caption}>Where they came from</p>
                        <Bars rows={a.from} total={a.checkedIn} />
                    </div>
                    <div className={panel}>
                        <p className={caption}>Districts</p>
                        <Bars rows={a.districts} total={a.checkedIn} />
                    </div>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div className={panel}>
                    <p className={caption}>{minuted ? 'Time on each item' : 'Time on each part'}</p>
                    <Bars rows={time.items.map((t) => ({ label: t.label, count: t.minutes, sub: t.lead }))} format={minutesLabel} />
                    {time.started && <p className="mt-4 text-[11px] font-medium text-gray-400">Started {time.started}, ended {time.ended}.</p>}
                </div>

                {p ? (
                    <div className={panel}>
                        <p className={caption}>{p.name}: day {p.day} of {p.days.length}</p>
                        <Bars rows={p.days.map((d) => ({ label: `${d.label}${d.date ? `, ${d.date}` : ''}`, count: d.count, strong: d.this }))} />
                        <div className="mt-5 grid grid-cols-3 gap-3 border-t border-gray-100 pt-4 text-center">
                            <Small label="Enrolled" value={p.enrolled} />
                            <Small label="Days for a certificate" value={p.required ?? '—'} />
                            <Small label="Certificates" value={p.certificates ?? 'Off'} />
                        </div>
                    </div>
                ) : (o.carried.total > 0 || o.agreed.total > 0) ? (
                    <div className={panel}>
                        <p className={caption}>Action points</p>
                        {o.carried.total > 0 && (
                            <>
                                <p className="mb-2 text-[12px] font-bold text-gray-600">From earlier meetings: {o.carried.total}</p>
                                <div className="mb-5 flex flex-wrap gap-2">
                                    <Status tone="good" label="Done here" value={o.carried.doneHere} />
                                    <Status tone="neutral" label="Still open" value={o.carried.open - o.carried.overdue} />
                                    <Status tone="bad" label="Overdue" value={o.carried.overdue} />
                                </div>
                            </>
                        )}
                        {o.owners.length > 0 && (
                            <>
                                <p className="mb-3 text-[12px] font-bold text-gray-600">Agreed here, by owner</p>
                                <Bars rows={o.owners} />
                            </>
                        )}
                    </div>
                ) : null}
            </div>
        </div>
    );
}

function Tile({ label, value, detail }: { label: string; value: number | string; detail: string }) {
    return (
        <div className="rounded-2xl border border-gray-100 bg-white p-5">
            <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">{label}</p>
            <p className="mt-2 text-3xl font-black tabular-nums text-brand">{value}</p>
            <p className="mt-1 text-[11px] font-medium text-gray-400">{detail}</p>
        </div>
    );
}

function Small({ label, value }: { label: string; value: number | string }) {
    return (
        <div>
            <p className="text-lg font-black tabular-nums text-brand">{value}</p>
            <p className="text-[9px] font-black uppercase tracking-wide text-gray-400">{label}</p>
        </div>
    );
}

/** Horizontal bars, longest first as given; the value written at the end of each. */
function Bars({ rows, total, format = String }: { rows: (Count & { sub?: string | null; strong?: boolean })[]; total?: number; format?: (n: number) => string }) {
    if (!rows.length) return <Nothing />;
    const max = Math.max(...rows.map((r) => r.count), 1);

    return (
        <ul className="space-y-2.5">
            {rows.map((r) => (
                <li key={r.label} className="group" title={`${r.label}: ${format(r.count)}${total ? ` (${Math.round((r.count / total) * 100)}%)` : ''}`}>
                    <div className="mb-1 flex items-baseline justify-between gap-3 text-[12px]">
                        <span className={cn('truncate font-bold', r.strong ? 'text-brand' : 'text-gray-600')}>{r.label}{r.sub && <span className="font-medium text-gray-400"> · {r.sub}</span>}</span>
                        <span className="shrink-0 font-black tabular-nums text-gray-700">{format(r.count)}{total ? <span className="ml-1 font-medium text-gray-400">{Math.round((r.count / total) * 100)}%</span> : null}</span>
                    </div>
                    <div className="h-2 rounded-full bg-gray-100">
                        <div className={cn('h-2 rounded-full transition-colors', r.strong === false ? 'bg-brand/40' : 'bg-brand', 'group-hover:bg-action')} style={{ width: `${Math.max(2, (r.count / max) * 100)}%` }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** Arrivals by 15 minutes: those from 15 minutes after the start in the lighter shade. */
function Arrivals({ rows }: { rows: (Count & { late: boolean })[] }) {
    const max = Math.max(...rows.map((r) => r.count), 1);

    return (
        <div>
            <div className="flex h-32 items-end gap-1.5">
                {rows.map((r) => (
                    <div key={r.label} className="group flex h-full flex-1 flex-col justify-end" title={`From ${r.label}: ${r.count}`}>
                        <span className="mb-1 text-center text-[10px] font-black tabular-nums text-gray-600">{r.count || ''}</span>
                        <div className={cn('rounded-t-[4px] transition-colors group-hover:bg-action', r.late ? 'bg-brand/40' : 'bg-brand')} style={{ height: `${(r.count / max) * 100}%`, minHeight: r.count ? 4 : 0 }} />
                    </div>
                ))}
            </div>
            <div className="mt-1 flex gap-1.5 border-t border-gray-200 pt-1">
                {rows.map((r) => <span key={r.label} className="flex-1 text-center text-[9px] font-bold tabular-nums text-gray-400">{r.label}</span>)}
            </div>
            <div className="mt-3 flex gap-4 text-[10px] font-bold text-gray-500">
                <span className="flex items-center gap-1.5"><span className="size-2.5 rounded-sm bg-brand" />By 15 minutes in</span>
                <span className="flex items-center gap-1.5"><span className="size-2.5 rounded-sm bg-brand/40" />Later</span>
            </div>
        </div>
    );
}

function Status({ tone, label, value }: { tone: 'good' | 'neutral' | 'bad'; label: string; value: number }) {
    return (
        <span className={cn('rounded-full px-3 py-1.5 text-[11px] font-black',
            tone === 'good' && 'bg-emerald-50 text-emerald-700', tone === 'neutral' && 'bg-gray-100 text-gray-600', tone === 'bad' && 'bg-rose-50 text-rose-700')}>
            {label}: <span className="tabular-nums">{value}</span>
        </span>
    );
}

function Nothing() {
    return <p className="text-[12px] italic text-gray-400">Nothing recorded.</p>;
}

/** The first two sentences of a write-up. */
function firstSentences(text: string) {
    const parts = text.replace(/\s+/g, ' ').trim().match(/[^.!?]+[.!?]+/g);
    return parts ? parts.slice(0, 2).join(' ').trim() : text;
}
