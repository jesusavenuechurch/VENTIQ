import { useForm } from '@inertiajs/react';
import { PenLine } from 'lucide-react';
import { useRef, useState } from 'react';
import SessionsLayout, { field, fieldLabel, primaryButton } from '@/Layouts/SessionsLayout';
import { SignaturePad, type SignaturePadHandle } from '@/components/signature-pad';

interface Props {
    allowed: boolean;
    signature: string | null;
    title: string;
    updated: string | null;
    name: string;
}

/** Your signature for certificates: drawn once, on every certificate you sign after. */
export default function Signature({ allowed, signature, title, updated, name }: Props) {
    const pad = useRef<SignaturePadHandle>(null);
    const [redrawing, setRedrawing] = useState(!signature);
    const [drawn, setDrawn] = useState(false);
    const form = useForm({ signature: '', title });

    if (!allowed) {
        return (
            <SessionsLayout title="My signature" crumbs={[['Programmes', route('programmes.index')], ['My signature']]} width="max-w-2xl">
                <div className="rounded-2xl border border-dashed border-gray-200 py-14 text-center">
                    <PenLine className="mx-auto mb-4 size-8 text-gray-300" />
                    <p className="text-sm font-bold text-gray-500">You don't sign certificates</p>
                    <p className="mx-auto mt-1 max-w-sm text-[12px] text-gray-400">An Admin can allow you on the Team page. Then your signature can go on your organization's certificates.</p>
                </div>
            </SessionsLayout>
        );
    }

    return (
        <SessionsLayout title="My signature" subtitle="It goes on the certificates you sign, from the next one issued." crumbs={[['Programmes', route('programmes.index')], ['My signature']]} width="max-w-2xl">
            <form
                className="space-y-6 rounded-2xl border border-gray-100 bg-white p-6 md:p-8"
                onSubmit={(e) => {
                    e.preventDefault();
                    const image = redrawing ? pad.current?.toDataUrl() ?? '' : '';
                    form.transform((d) => ({ ...d, signature: image }));
                    form.put(route('sessions.signature.update'), { preserveScroll: true, onSuccess: () => setRedrawing(false) });
                }}
            >
                {redrawing ? (
                    <div>
                        <p className={fieldLabel}>Draw your signature</p>
                        <SignaturePad ref={pad} onChange={setDrawn} />
                        <p className="ml-1 mt-2 text-[11px] text-gray-400">With a finger on a phone or tablet, or the mouse. Certificates already issued keep the signature they had.</p>
                    </div>
                ) : (
                    <div>
                        <p className={fieldLabel}>Your signature</p>
                        <div className="flex h-48 items-center justify-center rounded-2xl border border-gray-100 bg-gray-50">{signature && <img src={signature} alt="Your signature" className="max-h-36 max-w-[80%]" />}</div>
                        <p className="ml-1 mt-2 flex flex-wrap items-center gap-x-3 text-[11px] text-gray-400">
                            {updated && <span>Drawn {updated}</span>}
                            <button type="button" onClick={() => setRedrawing(true)} className="font-bold text-brand underline">Draw it again</button>
                        </p>
                    </div>
                )}
                {form.errors.signature && <p role="alert" className="text-[12px] font-bold text-rose-600">{form.errors.signature}</p>}
                <div>
                    <label htmlFor="sig-title" className={fieldLabel}>Under your name</label>
                    <input id="sig-title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="e.g. Programme Director" className={field} />
                    <p className="ml-1 mt-1 text-[11px] text-gray-400">Printed as: {name}{form.data.title && `, ${form.data.title}`}</p>
                </div>
                <button disabled={form.processing || (redrawing && !drawn)} className={primaryButton}>{redrawing ? 'Save my signature' : 'Save'}</button>
            </form>
        </SessionsLayout>
    );
}
