import { Check, LoaderCircle, RotateCcw, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { json } from '@/lib/http';
import { cn } from '@/lib/utils';

interface Details {
    name: string;
    category: string;
    date: string;
    venue: string;
    audience: string;
    notes: string;
    tone: string;
}

interface Result {
    jobId: string;
    status: 'pending' | 'completed' | 'failed';
    error: string | null;
    input: Partial<Details>;
    content: { description: string; tagline: string; titles: string[] } | null;
}

export interface Written {
    name: string;
    tagline: string;
    description: string;
}

const CATEGORIES = [
    '⛪ Church / Spiritual', '🎤 Conference / Seminar', '💼 Corporate / Business', '📚 Workshop / Training',
    '🌟 Youth Event', '❤️ Fundraiser / Charity', '🎵 Music / Entertainment', '🏘️ Community / Cultural',
    '⚽ Sports / Fitness', '🎓 School / Academic', '🏛️ Government / Official', '🎉 Private / Social',
];

const TONES: [string, string][] = [
    ['professional', 'Professional'],
    ['exciting and energetic', 'Exciting & energetic'],
    ['spiritual and uplifting', 'Spiritual & uplifting'],
    ['formal and corporate', 'Formal & corporate'],
    ['warm and community-focused', 'Warm & community'],
    ['youthful and fun', 'Youthful & fun'],
];

const ADJUST: [string, string][] = [
    ['more professional and formal', 'More professional'],
    ['more exciting and energetic', 'More exciting'],
    ['more spiritual and uplifting', 'More spiritual'],
    ['shorter and more concise', 'Shorter'],
    ['warmer and more community-focused', 'Warmer'],
];

// "⛪ Church / Spiritual" is sent without its emoji.
const plain = (category: string) => category.replace(/^\P{L}+/u, '');

/**
 * Ventiq Assist on the event form: from a few details, write the name,
 * tagline and description, then fill them into the form.
 */
export function EventWriter({ open, onOpenChange, prefill, onFill }: { open: boolean; onOpenChange: (open: boolean) => void; prefill: Partial<Details>; onFill: (written: Written) => void }) {
    const [input, setInput] = useState<Details>({ name: '', category: '', date: '', venue: '', audience: '', notes: '', tone: 'professional' });
    const [result, setResult] = useState<Result | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [title, setTitle] = useState('');
    const [loaded, setLoaded] = useState(false);

    // On first open: pick up the last one from the past two hours, else start from the form.
    useEffect(() => {
        if (!open || loaded) return;
        setLoaded(true);
        void json<{ result: Result | null }>(route('assist.event-content.latest')).then(({ ok, data }) => {
            const recent = ok ? data.result : null;
            if (recent && recent.status !== 'failed') {
                setInput((i) => ({ ...i, ...recent.input }));
                setResult(recent);
                setTitle(recent.input.name ?? '');
            } else {
                setInput((i) => ({ ...i, ...Object.fromEntries(Object.entries(prefill).filter(([, v]) => v)) }));
            }
        });
    }, [open, loaded, prefill]);

    // While it's writing, check every 2 seconds.
    useEffect(() => {
        if (!open || result?.status !== 'pending') return;
        const t = window.setInterval(async () => {
            const { ok, data } = await json<{ result: Result }>(route('assist.event-content.show', result.jobId));
            if (ok) setResult(data.result);
        }, 2000);
        return () => window.clearInterval(t);
    }, [open, result]);

    async function write(tone = input.tone) {
        setErrors({});
        const body = { ...input, tone, category: plain(input.category) };
        const { ok, status, data } = await json<{ result: Result; errors?: Record<string, string[]> }>(route('assist.event-content.store'), { method: 'POST', body });
        if (ok) {
            setInput((i) => ({ ...i, tone }));
            setResult(data.result);
            setTitle(input.name);
        } else if (status === 422 && data.errors) {
            setErrors(Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, v[0]])));
        } else {
            setResult({ jobId: '', status: 'failed', error: status === 429 ? 'Too many tries in a row. Wait a minute and try again.' : 'Something went wrong. Please try again.', input, content: null });
        }
    }

    function fill() {
        if (!result?.content) return;
        onFill({ name: title || input.name, tagline: result.content.tagline, description: result.content.description });
        onOpenChange(false);
    }

    const set = (key: keyof Details) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => setInput((i) => ({ ...i, [key]: e.target.value }));
    const writing = result?.status === 'pending';
    const content = result?.status === 'completed' ? result.content : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col rounded-[2rem]">
                <div className="flex items-center gap-3 border-b border-gray-100 px-7 py-5">
                    <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-lilac text-lilac-ink"><Sparkles className="size-4" /></div>
                    <div>
                        <DialogTitle className="text-base normal-case not-italic tracking-normal">Ventiq Assist</DialogTitle>
                        <p className="text-[12px] text-gray-500">Tell me about your event and I'll write the details.</p>
                    </div>
                </div>

                <div className="flex-1 space-y-5 overflow-y-auto px-7 py-6">
                    {writing ? (
                        <div className="space-y-3 py-10 text-center">
                            <LoaderCircle className="mx-auto size-8 animate-spin text-brand" />
                            <p className="text-sm font-bold text-brand">Writing your event content…</p>
                            <p className="text-[12px] text-gray-500">This takes a few seconds. You can wait here.</p>
                        </div>
                    ) : content ? (
                        <div className="space-y-4">
                            {content.titles.length > 0 && (
                                <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <p className="mb-2 text-[11px] font-bold text-gray-400">Suggested name (pick one or keep yours)</p>
                                    {[input.name, ...content.titles.filter((t) => t !== input.name)].map((t, i) => (
                                        <label key={t} className="flex cursor-pointer items-center gap-2 py-1 text-[13px] text-gray-800">
                                            <input type="radio" name="title" checked={title === t} onChange={() => setTitle(t)} className="accent-action" />
                                            {t} {i === 0 && <span className="text-[11px] text-gray-400">(yours)</span>}
                                        </label>
                                    ))}
                                </div>
                            )}
                            {content.tagline && (
                                <div className="rounded-2xl bg-lilac/50 p-4">
                                    <p className="mb-1 text-[11px] font-bold text-lilac-ink">Tagline</p>
                                    <p className="text-[13px] italic text-gray-800">"{content.tagline}"</p>
                                </div>
                            )}
                            {content.description && (
                                <div className="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <p className="mb-1 text-[11px] font-bold text-gray-400">Description</p>
                                    <p className="whitespace-pre-line text-[13px] text-gray-700">{content.description}</p>
                                </div>
                            )}

                            <Button type="button" variant="brand" className="w-full py-3.5 text-[11px]" onClick={fill}>
                                <Check className="size-4" />Fill my event form
                            </Button>

                            <div>
                                <p className="mb-2 text-[11px] font-bold text-gray-500">Not quite right? Write it again:</p>
                                <div className="flex flex-wrap gap-2">
                                    {ADJUST.map(([tone, label]) => (
                                        <button key={tone} type="button" onClick={() => void write(tone)} className="rounded-full border border-gray-200 px-3 py-1.5 text-[11px] font-bold text-gray-600 hover:border-brand hover:text-brand">
                                            {label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <button type="button" onClick={() => setResult(null)} className="flex w-full items-center justify-center gap-1.5 rounded-2xl border border-dashed border-gray-300 py-2 text-[11px] font-bold text-gray-500 hover:text-gray-700">
                                <RotateCcw className="size-3" />Start over
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <Field label="Event name" htmlFor="assist-name" error={errors.name}>
                                <Input id="assist-name" value={input.name} onChange={set('name')} placeholder="e.g. Youth Prayer Night" className="rounded-2xl px-4 py-3" />
                            </Field>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Category" htmlFor="assist-category" error={errors.category}>
                                    <NativeSelect id="assist-category" value={input.category} onChange={set('category')}>
                                        <option value="">Choose…</option>
                                        {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                                    </NativeSelect>
                                </Field>
                                <Field label="Tone" htmlFor="assist-tone">
                                    <NativeSelect id="assist-tone" value={input.tone} onChange={set('tone')}>
                                        {TONES.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                    </NativeSelect>
                                </Field>
                                <Field label="Venue" htmlFor="assist-venue">
                                    <Input id="assist-venue" value={input.venue} onChange={set('venue')} placeholder="e.g. Maseru City Hall" className="rounded-2xl px-4 py-3" />
                                </Field>
                                <Field label="Who it's for" htmlFor="assist-audience">
                                    <Input id="assist-audience" value={input.audience} onChange={set('audience')} placeholder="e.g. Youth, business owners" className="rounded-2xl px-4 py-3" />
                                </Field>
                            </div>
                            <Field label={<>Anything else <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="assist-notes">
                                <Textarea id="assist-notes" rows={2} value={input.notes} onChange={set('notes')} placeholder="Speakers, themes, dress code, anything special…" />
                            </Field>
                            {result?.status === 'failed' && <p className="rounded-2xl border border-rose-100 bg-rose-50 p-3 text-[12px] font-bold text-rose-700">{result.error}</p>}
                            <Button type="button" variant="brand" className={cn('w-full py-3.5 text-[11px]')} onClick={() => void write()}>
                                <Sparkles className="size-4" />Write it
                            </Button>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
