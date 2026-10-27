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
    // Or a picture of one already on paper: white paper made see-through.
    const [uploaded, setUploaded] = useState<string | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);

    function readPicture(file: File | undefined) {
        setUploadError(null);
        if (!file) return;
        if (!file.type.startsWith('image/')) {
            setUploadError('Choose a photo or picture (PNG or JPG). For a PDF, take a screenshot of the signature first.');
            return;
        }
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, 800 / img.width);
            const c = document.createElement('canvas');
            c.width = Math.round(img.width * scale);
            c.height = Math.round(img.height * scale);
            const ctx = c.getContext('2d');
            if (!ctx) return;
            ctx.drawImage(img, 0, 0, c.width, c.height);
            const px = ctx.getImageData(0, 0, c.width, c.height);
            for (let i = 0; i < px.data.length; i += 4) {
                const light = (px.data[i] + px.data[i + 1] + px.data[i + 2]) / 3;
                if (light > 190) px.data[i + 3] = 0;
            }
            ctx.putImageData(px, 0, 0);
            setUploaded(c.toDataURL('image/png'));
            URL.revokeObjectURL(img.src);
        };
        img.onerror = () => setUploadError("That picture couldn't be opened. Try another one.");
        img.src = URL.createObjectURL(file);
    }

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
                    const image = redrawing ? uploaded ?? pad.current?.toDataUrl() ?? '' : '';
                    form.transform((d) => ({ ...d, signature: image }));
                    form.put(route('sessions.signature.update'), { preserveScroll: true, onSuccess: () => { setRedrawing(false); setUploaded(null); } });
                }}
            >
                {redrawing ? (
                    <div>
                        <p className={fieldLabel}>{uploaded ? 'Your signature, from the picture' : 'Draw your signature'}</p>
                        {uploaded ? (
                            <div className="flex h-48 items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 bg-white"><img src={uploaded} alt="Your signature, from the picture" className="max-h-36 max-w-[80%]" /></div>
                        ) : (
                            <SignaturePad ref={pad} onChange={setDrawn} />
                        )}
                        <p className="ml-1 mt-2 text-[11px] text-gray-400">
                            {uploaded ? (
                                <button type="button" onClick={() => setUploaded(null)} className="font-bold text-brand underline">Draw it instead</button>
                            ) : (
                                <>With a finger or pen on a phone or tablet, or the mouse. </>
                            )}
                            {' '}Certificates already issued keep the signature they had.
                        </p>
                        {!uploaded && (
                            <label className="ml-1 mt-3 inline-flex cursor-pointer items-center gap-2 text-[11px] font-bold text-brand">
                                <input type="file" accept="image/*" className="sr-only" onChange={(e) => readPicture(e.target.files?.[0])} />
                                <span className="rounded-full border border-gray-200 px-3 py-1.5 hover:border-brand">Or upload a picture of your signature</span>
                                <span className="font-medium text-gray-400">signed on white paper, PNG or JPG</span>
                            </label>
                        )}
                        {uploadError && <p role="alert" className="ml-1 mt-2 text-[11px] font-bold text-rose-600">{uploadError}</p>}
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
                <button disabled={form.processing || (redrawing && !drawn && !uploaded)} className={primaryButton}>{redrawing ? 'Save my signature' : 'Save'}</button>
            </form>
        </SessionsLayout>
    );
}
