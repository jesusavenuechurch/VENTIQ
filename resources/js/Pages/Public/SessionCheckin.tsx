import { useForm } from '@inertiajs/react';
import { Camera, CircleCheck, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { authButton, authInput } from '@/Layouts/AuthLayout';

interface Props {
    session: { title: string; organization: string | null; token: string };
    /** The name of whoever just checked in, from the redirect. */
    checkedIn: string | null;
}

/** The page the session's QR code opens: check yourself in. */
export default function SessionCheckin({ session, checkedIn }: Props) {
    const form = useForm<{ full_name: string; email: string; phone: string; institution: string; position: string; photo: File | null }>({
        full_name: '',
        email: '',
        phone: '',
        institution: '',
        position: '',
        photo: null,
    });
    const [preview, setPreview] = useState<string | null>(null);
    useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);
    const firstError = Object.values(form.errors)[0];

    return (
        <GuestLayout label="Check in" context={session.organization ?? undefined}>
            <div className="mx-auto max-w-sm py-8">
                <div className="rounded-[2rem] bg-white p-8 shadow-xl">
                    {checkedIn ? (
                        <div className="py-6 text-center">
                            <CircleCheck className="mx-auto mb-4 size-14 text-emerald-500" />
                            <h1 className="text-lg font-black uppercase">Welcome, {checkedIn}</h1>
                            <p className="mt-2 text-[12px] font-bold text-gray-400">You're checked in to</p>
                            <p className="mt-1 text-sm font-bold text-gray-600">{session.title}</p>
                            <p className="mt-6 text-[11px] text-gray-400">Your attendance card comes by email when the session ends.</p>
                        </div>
                    ) : (
                        <>
                            <h1 className="text-lg font-black uppercase tracking-tight">{session.title}</h1>
                            <p className="mb-6 mt-1 text-[12px] font-medium text-gray-500">Your details, to check in. Checked in already? Use the same email to change them.</p>
                            {firstError && <div role="alert" className="mb-4 rounded-xl bg-rose-50 p-3 text-[12px] font-bold text-rose-600">{firstError}</div>}
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    form.post(route('public.session-checkin.submit', session.token), { forceFormData: true, preserveScroll: true });
                                }}
                            >
                                <input aria-label="Full name" required autoFocus autoComplete="name" value={form.data.full_name} onChange={(e) => form.setData('full_name', e.target.value)} placeholder="Full name" className={authInput} />
                                <input aria-label="Email" required type="email" autoComplete="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder="Email" className={authInput} />
                                <input aria-label="Phone" type="tel" autoComplete="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="Phone (optional)" className={authInput} />
                                <input aria-label="Organization or institution" value={form.data.institution} onChange={(e) => form.setData('institution', e.target.value)} placeholder="Organization or institution" className={authInput} />
                                <input aria-label="Position or role" value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} placeholder="Position or role" className={authInput} />

                                <label className="flex cursor-pointer items-center gap-3 rounded-2xl border-2 border-dashed border-gray-100 p-3">
                                    <span className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-gray-300">
                                        {preview ? <img src={preview} alt="" className="size-full object-cover" /> : <Camera className="size-5" />}
                                    </span>
                                    <span className="text-[11px] font-bold text-gray-500">
                                        {form.data.photo ? 'Change photo' : 'Add a photo for your attendance card (optional)'}
                                    </span>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        capture="user"
                                        className="sr-only"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0] ?? null;
                                            form.setData('photo', file);
                                            setPreview(file ? URL.createObjectURL(file) : null);
                                        }}
                                    />
                                </label>

                                <button disabled={form.processing} className={authButton}>{form.processing ? <LoaderCircle className="size-5 animate-spin" /> : 'Check in'}</button>
                            </form>
                        </>
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}
