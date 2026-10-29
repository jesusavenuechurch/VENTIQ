import { router, useForm } from '@inertiajs/react';
import { CalendarDays, Check, ListTodo, Pencil, Plus, RotateCcw, Sparkles, Trash2, UserRound } from 'lucide-react';
import { useState } from 'react';
import { field, fieldLabel } from '@/Layouts/SessionsLayout';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';

export interface ActionPointCard {
    id: number;
    task: string;
    owner: string | null;
    ownerKey: string;
    ownerName: string | null;
    due: string | null;
    dueLabel: string | null;
    overdue: boolean;
    status: 'proposed' | 'open' | 'done' | 'dropped';
    source: 'ai' | 'manual';
    session: { id: number; title: string; date: string | null } | null;
    doneLabel: string | null;
}

export interface Owner {
    value: string;
    label: string;
}

/**
 * One action point: what, who, by when. Suggested ones (the AI's) are
 * lilac until someone confirms them; done ones are struck through.
 */
export function ActionPointRow({
    point,
    owners,
    canEdit,
    doneIn,
    showSession = false,
}: {
    point: ActionPointCard;
    owners: Owner[];
    canEdit: boolean;
    /** Ticked off in this meeting. */
    doneIn?: number;
    showSession?: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const done = point.status === 'done';
    const proposed = point.status === 'proposed';
    const opts = { preserveScroll: true };

    return (
        <li className={cn('flex items-start gap-3 px-4 py-3', proposed && 'bg-lilac/50')}>
            {canEdit && !proposed ? (
                <button
                    type="button"
                    aria-label={done ? `Open "${point.task}" again` : `Mark "${point.task}" done`}
                    onClick={() => router.post(route(done ? 'sessions.actions.reopen' : 'sessions.actions.done', point.id), doneIn && !done ? { session_id: doneIn } : {}, opts)}
                    className={cn('mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition-colors', done ? 'border-mint-ink bg-mint-ink text-white' : 'border-gray-300 hover:border-mint-ink')}
                >
                    {done && <Check className="size-3.5" />}
                </button>
            ) : (
                <span className={cn('mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-md', proposed ? 'text-lilac-ink' : 'text-gray-300')}>{proposed ? <Sparkles className="size-4" /> : <ListTodo className="size-4" />}</span>
            )}
            <div className="min-w-0 flex-1">
                {canEdit ? (
                    <button type="button" onClick={() => setEditing(true)} title="Open to change who, what or when" className={cn('text-left text-[13px] font-bold leading-snug text-brand hover:text-action hover:underline', done && 'text-gray-400 line-through')}>{point.task}</button>
                ) : (
                    <p className={cn('text-[13px] font-bold leading-snug text-brand', done && 'text-gray-400 line-through')}>{point.task}</p>
                )}
                <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-gray-400">
                    <span className="inline-flex items-center gap-1"><UserRound className="size-3" />{point.owner ?? 'No one yet'}</span>
                    {point.dueLabel && <span className={cn('inline-flex items-center gap-1', point.overdue && 'font-bold text-rose-600')}><CalendarDays className="size-3" />{point.overdue ? `Overdue, ${point.dueLabel}` : `By ${point.dueLabel}`}</span>}
                    {done && point.doneLabel && <span className="text-mint-ink">Done {point.doneLabel}</span>}
                    {proposed && <span className="font-bold text-lilac-ink">Suggested by Ventiq Assist</span>}
                    {showSession && point.session && <span>From {point.session.title}{point.session.date && `, ${point.session.date}`}</span>}
                </p>
            </div>
            {canEdit && (
                <span className="flex shrink-0 items-center gap-1">
                    {proposed && (
                        <button type="button" onClick={() => router.patch(route('sessions.actions.update', point.id), { task: point.task, owner: point.ownerKey, owner_name: point.ownerName, due_date: point.due }, opts)} className="rounded-full bg-lilac-ink px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white">Keep</button>
                    )}
                    <button type="button" aria-label="Change" onClick={() => setEditing(true)} className="rounded-full p-2 text-gray-500 hover:bg-gray-50 hover:text-brand"><Pencil className="size-3.5" /></button>
                    <button type="button" aria-label="Remove" onClick={() => router.delete(route('sessions.actions.destroy', point.id), opts)} className="rounded-full p-2 text-gray-400 hover:bg-gray-50 hover:text-rose-600"><Trash2 className="size-3.5" /></button>
                </span>
            )}
            {editing && <ActionPointDialog point={point} owners={owners} onClose={() => setEditing(false)} />}
        </li>
    );
}

/** Add one (to a session) or change one. */
export function ActionPointDialog({ point, sessionId, owners, onClose }: { point?: ActionPointCard; sessionId?: number; owners: Owner[]; onClose: () => void }) {
    const form = useForm({ task: point?.task ?? '', owner: point?.ownerKey ?? '', owner_name: point?.ownerName ?? '', due_date: point?.due ?? '' });
    const [typed, setTyped] = useState(!point?.ownerKey && !!point?.ownerName);

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent aria-describedby="action-point-help" className="max-w-lg">
                <form
                    className="space-y-4 p-6 sm:p-8"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((d) => (typed ? { ...d, owner: '' } : { ...d, owner_name: '' }));
                        const done = { preserveScroll: true, onSuccess: onClose };
                        point ? form.patch(route('sessions.actions.update', point.id), done) : form.post(route('sessions.actions.store', sessionId!), done);
                    }}
                >
                    <div>
                        <DialogTitle className="pr-10 text-xl normal-case not-italic tracking-tight">{point ? 'Change the action point' : 'Add an action point'}</DialogTitle>
                        <DialogDescription id="action-point-help" className="mt-1 text-[13px] font-medium normal-case tracking-normal text-gray-500">Who has it gets a reminder before it's due.</DialogDescription>
                    </div>
                    <div>
                        <label htmlFor="ap-task" className={fieldLabel}>What's to be done</label>
                        <textarea id="ap-task" required rows={2} value={form.data.task} onChange={(e) => form.setData('task', e.target.value)} placeholder="e.g. Send the venue quotes to finance" className={cn(field, 'resize-none')} />
                        {form.errors.task && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-600">{form.errors.task}</p>}
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="ap-owner" className={fieldLabel}>Who</label>
                            {typed ? (
                                <input id="ap-owner" value={form.data.owner_name} onChange={(e) => form.setData('owner_name', e.target.value)} placeholder="Their name" className={field} />
                            ) : (
                                <NativeSelect id="ap-owner" value={form.data.owner} onChange={(e) => form.setData('owner', e.target.value)} className="border-gray-200 bg-white py-4 font-bold text-brand">
                                    <option value="">No one yet</option>
                                    {owners.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                                </NativeSelect>
                            )}
                            <button type="button" onClick={() => setTyped(!typed)} className="ml-1 mt-1 text-[11px] font-bold text-gray-400 underline hover:text-brand">{typed ? 'Pick from the team' : 'Someone else: type a name'}</button>
                        </div>
                        <div>
                            <label htmlFor="ap-due" className={fieldLabel}>By when</label>
                            <input id="ap-due" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} className={field} />
                        </div>
                    </div>
                    <button disabled={form.processing} className="w-full rounded-2xl bg-brand py-4 text-[11px] font-black uppercase tracking-[0.3em] text-white hover:bg-action disabled:opacity-50">{point ? 'Save' : 'Add'}</button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** A session's action points on its report: the suggestions to confirm, the agreed ones, and adding one. */
export function SessionActionPoints({ sessionId, points, carried, owners, canEdit, minutes = false }: { sessionId: number; points: ActionPointCard[]; carried: ActionPointCard[]; owners: Owner[]; canEdit: boolean; minutes?: boolean }) {
    const [adding, setAdding] = useState(false);
    const suggested = points.filter((p) => p.status === 'proposed').length;

    return (
        <div className="mb-6 rounded-2xl border border-gray-100 bg-white">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-50 px-5 py-4">
                <div>
                    <p className="flex items-center gap-2 text-[13px] font-black text-brand"><ListTodo className="size-4 text-action" />Action points</p>
                    {suggested > 0 && <p className="mt-0.5 text-[11px] text-lilac-ink">Ventiq Assist suggested {suggested}. Keep, change or remove each; {minutes ? 'confirming the minutes' : 'marking the report reviewed'} keeps the rest.</p>}
                </div>
                {canEdit && (
                    <span className="flex gap-2">
                        {suggested > 1 && <button type="button" onClick={() => router.post(route('sessions.actions.confirm', sessionId), {}, { preserveScroll: true })} className="rounded-full bg-lilac px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-lilac-ink">Keep all</button>}
                        <button type="button" onClick={() => setAdding(true)} className="inline-flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-brand hover:border-brand"><Plus className="size-3" />Add</button>
                    </span>
                )}
            </div>
            {points.length > 0 ? (
                <ul className="divide-y divide-gray-50">{points.map((p) => <ActionPointRow key={p.id} point={p} owners={owners} canEdit={canEdit} doneIn={sessionId} />)}</ul>
            ) : (
                <p className="px-5 py-6 text-[12px] italic text-gray-400">None from this session.</p>
            )}
            {carried.length > 0 && (
                <>
                    <p className="flex items-center gap-1.5 border-t border-gray-50 px-5 pb-1 pt-4 text-[10px] font-black uppercase tracking-widest text-gray-400"><RotateCcw className="size-3" />From earlier meetings</p>
                    <ul className="divide-y divide-gray-50">{carried.map((p) => <ActionPointRow key={p.id} point={p} owners={owners} canEdit={canEdit} doneIn={sessionId} showSession />)}</ul>
                </>
            )}
            {adding && <ActionPointDialog sessionId={sessionId} owners={owners} onClose={() => setAdding(false)} />}
        </div>
    );
}
