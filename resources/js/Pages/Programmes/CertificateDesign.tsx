import { Link, router, useForm } from '@inertiajs/react';
import { Download, LoaderCircle, PenLine, TriangleAlert } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import SessionsLayout, { field, fieldLabel, primaryButton, quietButton } from '@/Layouts/SessionsLayout';
import { NativeSelect } from '@/components/ui/native-select';
import { json } from '@/lib/http';
import { cn } from '@/lib/utils';

interface Design {
    layout: string;
    title: string;
    subtitle: string;
    wording: string;
    primary_color: string;
    accent_color: string;
    show_logo: boolean;
    signatories: number[];
}

interface Props {
    design: Design;
    programme: { id: number; name: string; own: boolean } | null;
    programmes: { id: number; name: string }[];
    signers: { id: number; name: string; title: string | null; signed: boolean }[];
    layouts: { value: string; label: string }[];
    placeholders: string[];
    hasLogo: boolean;
}

/** Colour pairs that work together: the main colour, and the accent. */
const PALETTES: [string, string, string][] = [
    ['VENTIQ', '#1D4069', '#F07F22'],
    ['Forest', '#14532d', '#ca8a04'],
    ['Maroon', '#7f1d1d', '#b45309'],
    ['Royal', '#312e81', '#0891b2'],
    ['Charcoal', '#1f2937', '#9ca3af'],
];

const PAGE = { width: 1122, height: 793 };

/** How certificates look: layout, words, colours, logo and who signs, with a live preview of the real thing. */
export default function CertificateDesign({ design, programme, programmes, signers, layouts, placeholders, hasLogo }: Props) {
    const form = useForm<Design>(design);
    const scope = programme ? { programme: programme.id } : {};
    const wording = useRef<HTMLTextAreaElement>(null);

    // The preview: the certificate's own view, a moment after each change.
    const [html, setHtml] = useState('');
    const [loading, setLoading] = useState(true);
    useEffect(() => {
        const t = window.setTimeout(async () => {
            setLoading(true);
            const { ok, data } = await json<{ html: string }>(route('sessions.certificates.design.preview'), { method: 'POST', body: { ...form.data, ...scope } }).catch(() => ({ ok: false, data: { html: '' } }));
            if (ok) setHtml(data.html);
            setLoading(false);
        }, 400);
        return () => window.clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(form.data)]);

    // The page shrunk to the preview's width.
    const frame = useRef<HTMLDivElement>(null);
    const [scale, setScale] = useState(0.5);
    useLayoutEffect(() => {
        const fit = () => frame.current && setScale(frame.current.clientWidth / PAGE.width);
        fit();
        window.addEventListener('resize', fit);
        return () => window.removeEventListener('resize', fit);
    }, []);

    const insert = (key: string) => {
        const el = wording.current;
        const token = `{${key}}`;
        const [start, end] = el ? [el.selectionStart, el.selectionEnd] : [form.data.wording.length, form.data.wording.length];
        form.setData('wording', form.data.wording.slice(0, start) + token + form.data.wording.slice(end));
        window.requestAnimationFrame(() => {
            el?.focus();
            el?.setSelectionRange(start + token.length, start + token.length);
        });
    };
    const signer = (slot: number, id: string) => {
        const next = [...form.data.signatories];
        next[slot] = Number(id);
        form.setData('signatories', next.filter((v, i) => v && next.indexOf(v) === i).slice(0, 2));
    };
    const unsigned = form.data.signatories.map((id) => signers.find((s) => s.id === id)).filter((s) => s && !s.signed);
    const firstError = Object.values(form.errors)[0];

    return (
        <SessionsLayout
            title="Certificate design"
            subtitle={programme ? `For ${programme.name}` : 'For every programme, unless one has its own'}
            crumbs={[['Programmes', route('programmes.index')], ...(programme ? [[programme.name, route('programmes.show', programme.id)] as [string, string]] : []), ['Certificate design']]}
            width="max-w-6xl"
            actions={<a href={route('sessions.certificates.design.sample', scope)} className={quietButton}><Download className="size-3.5" />Sample PDF</a>}
        >
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <form
                    className="space-y-6 lg:col-span-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((d) => ({ ...d, ...scope }));
                        form.put(route('sessions.certificates.design.update'), { preserveScroll: true });
                    }}
                >
                    {programmes.length > 0 && (
                        <div>
                            <label htmlFor="scope" className={fieldLabel}>Design for</label>
                            <NativeSelect id="scope" value={programme?.id ?? ''} onChange={(e) => router.get(route('sessions.certificates.design'), e.target.value ? { programme: e.target.value } : {})} className="border-gray-200 bg-white py-3 font-bold text-brand">
                                <option value="">Every programme (the organization's)</option>
                                {programmes.map((p) => <option key={p.id} value={p.id}>{p.name} only</option>)}
                            </NativeSelect>
                            {programme && (
                                <p className="ml-1 mt-1.5 text-[11px] text-gray-400">
                                    {programme.own ? (
                                        <>This programme has its own design. <button type="button" onClick={() => router.delete(route('sessions.certificates.design.reset', scope))} className="font-bold text-brand underline">Use the organization's instead</button></>
                                    ) : (
                                        "It uses the organization's design now; saving gives it its own."
                                    )}
                                </p>
                            )}
                        </div>
                    )}

                    {firstError && <p role="alert" className="rounded-xl bg-rose-50 p-3 text-[12px] font-bold text-rose-600">{firstError}</p>}

                    <div>
                        <p className={fieldLabel}>Layout</p>
                        <div className="grid grid-cols-3 gap-2">
                            {layouts.map((l) => (
                                <button key={l.value} type="button" onClick={() => form.setData('layout', l.value)} aria-pressed={form.data.layout === l.value} className={cn('rounded-2xl border-2 p-2 text-center transition-colors', form.data.layout === l.value ? 'border-action bg-action-soft' : 'border-gray-100 bg-white hover:border-gray-200')}>
                                    <LayoutThumb layout={l.value} primary={form.data.primary_color} accent={form.data.accent_color} />
                                    <span className="mt-1.5 block text-[10px] font-black uppercase tracking-widest text-brand">{l.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <p className={fieldLabel}>Colours</p>
                        <div className="flex flex-wrap items-center gap-2">
                            {PALETTES.map(([name, p, a]) => (
                                <button key={name} type="button" title={name} aria-label={`${name} colours`} onClick={() => form.setData((d) => ({ ...d, primary_color: p, accent_color: a }))} className={cn('flex overflow-hidden rounded-full border-2', form.data.primary_color === p && form.data.accent_color === a ? 'border-brand' : 'border-transparent')}>
                                    <span className="size-6" style={{ background: p }} />
                                    <span className="size-6" style={{ background: a }} />
                                </button>
                            ))}
                            <label className="ml-auto flex items-center gap-1.5 text-[10px] font-bold text-gray-400">
                                <input type="color" aria-label="Main colour" value={form.data.primary_color} onChange={(e) => form.setData('primary_color', e.target.value)} className="size-7 cursor-pointer rounded border-0 bg-transparent p-0" />
                                <input type="color" aria-label="Accent colour" value={form.data.accent_color} onChange={(e) => form.setData('accent_color', e.target.value)} className="size-7 cursor-pointer rounded border-0 bg-transparent p-0" />
                                Your own
                            </label>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="title" className={fieldLabel}>Title</label>
                            <input id="title" required maxLength={60} value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={field} />
                        </div>
                        <div>
                            <label htmlFor="subtitle" className={fieldLabel}>Under it</label>
                            <input id="subtitle" maxLength={80} value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} placeholder="e.g. of completion" className={field} />
                        </div>
                    </div>

                    <div>
                        <label htmlFor="wording" className={fieldLabel}>Wording, after the person's name</label>
                        <textarea id="wording" ref={wording} required rows={4} maxLength={600} value={form.data.wording} onChange={(e) => form.setData('wording', e.target.value)} className={cn(field, 'resize-y text-[13px] font-medium leading-relaxed')} />
                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                            <span className="text-[10px] font-bold text-gray-400">Add:</span>
                            {placeholders.map((p) => <button key={p} type="button" onClick={() => insert(p)} className="rounded-full bg-mint px-2.5 py-1 font-mono text-[10px] font-bold text-mint-ink hover:bg-mint-ink hover:text-white">{`{${p}}`}</button>)}
                        </div>
                        <p className="ml-1 mt-1.5 text-[11px] text-gray-400">Each is filled in for the person and programme, in bold. One with nothing to fill it drops out ("at {'{venue}'}" when there's no venue).</p>
                    </div>

                    <label className={cn('flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-5 py-4', hasLogo ? 'cursor-pointer' : 'opacity-60')}>
                        <span>
                            <span className="block text-[11px] font-black uppercase tracking-wide text-brand">Your logo</span>
                            <span className="mt-0.5 block text-[11px] font-medium text-gray-400">{hasLogo ? 'From your organization settings' : <>Add one in <Link href={route('organizer.organization.edit')} className="underline">organization settings</Link> first</>}</span>
                        </span>
                        <input type="checkbox" disabled={!hasLogo} checked={form.data.show_logo && hasLogo} onChange={(e) => form.setData('show_logo', e.target.checked)} className="size-5 accent-brand" />
                    </label>

                    <div>
                        <p className={fieldLabel}>Signed by</p>
                        {signers.length === 0 ? (
                            <p className="rounded-2xl bg-gray-50 p-4 text-[12px] text-gray-500">No one can sign yet. An Admin allows people on the <Link href={route('organizer.team.index')} className="font-bold text-brand underline">Team page</Link>; each then draws their signature once.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {[0, 1].map((slot) => (
                                    <NativeSelect key={slot} aria-label={`Signatory ${slot + 1}`} value={form.data.signatories[slot] ?? ''} onChange={(e) => signer(slot, e.target.value)} disabled={slot === 1 && !form.data.signatories[0]} className="border-gray-200 bg-white py-3 font-bold text-brand">
                                        <option value="">{slot === 0 ? 'No one' : 'No second signature'}</option>
                                        {signers.filter((s) => slot === 0 ? s.id !== form.data.signatories[1] : s.id !== form.data.signatories[0]).map((s) => <option key={s.id} value={s.id}>{s.name}{s.title ? `, ${s.title}` : ''}</option>)}
                                    </NativeSelect>
                                ))}
                            </div>
                        )}
                        {unsigned.length > 0 && (
                            <p className="mt-2 flex items-start gap-1.5 text-[11px] font-bold text-amber-700"><TriangleAlert className="mt-0.5 size-3.5 shrink-0" />{unsigned.map((s) => s!.name).join(' and ')} {unsigned.length === 1 ? "hasn't" : "haven't"} drawn a signature yet: certificates issued before then show just the name.</p>
                        )}
                        <Link href={route('sessions.signature')} className="ml-1 mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-gray-400 hover:text-brand"><PenLine className="size-3" />My signature</Link>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <button disabled={form.processing || !form.isDirty} className={primaryButton}>{form.processing ? 'Saving…' : 'Save the design'}</button>
                        {form.isDirty && <span className="text-[11px] text-gray-400">Certificates already issued keep the design they had.</span>}
                    </div>
                </form>

                <div className="order-first lg:order-none lg:col-span-3">
                    <div className="lg:sticky lg:top-6">
                        <p className="mb-2 flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Preview {loading && <LoaderCircle className="size-3 animate-spin" />}</p>
                        <div ref={frame} className="relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg" style={{ height: PAGE.height * scale }}>
                            {html && <iframe title="Certificate preview" srcDoc={html} sandbox="" className="pointer-events-none absolute left-0 top-0 origin-top-left border-0" style={{ width: PAGE.width, height: PAGE.height, transform: `scale(${scale})` }} />}
                        </div>
                        <p className="mt-2 text-[11px] text-gray-400">With a sample name. The PDF is made from the same design.</p>
                    </div>
                </div>
            </div>
        </SessionsLayout>
    );
}

/** A small sketch of each layout, in the chosen colours. */
function LayoutThumb({ layout, primary, accent }: { layout: string; primary: string; accent: string }) {
    return (
        <span className="relative block aspect-[1.414] overflow-hidden rounded-lg border border-gray-100 bg-white">
            {layout === 'banner' && (
                <>
                    <span className="absolute inset-x-0 top-0 h-[38%] rounded-b-[40%]" style={{ background: primary }} />
                    <span className="absolute inset-x-0 top-[38%] h-[5%]" style={{ background: accent }} />
                </>
            )}
            {layout === 'classic' && (
                <span className="absolute inset-[8%] border-[3px] border-double" style={{ borderColor: primary }}>
                    <span className="absolute inset-[6%] border" style={{ borderColor: accent }} />
                </span>
            )}
            {layout === 'minimal' && (
                <>
                    <span className="absolute inset-y-0 left-0 w-[7%]" style={{ background: primary }} />
                    <span className="absolute inset-y-0 left-[7%] w-[2%]" style={{ background: accent }} />
                </>
            )}
            <span className={cn('absolute h-[6%] w-[44%] rounded-full bg-gray-300', layout === 'minimal' ? 'left-[20%] top-[56%]' : 'left-[28%] top-[56%]')} />
            <span className={cn('absolute h-[4%] w-[56%] rounded-full bg-gray-200', layout === 'minimal' ? 'left-[20%] top-[70%]' : 'left-[22%] top-[70%]')} />
        </span>
    );
}
