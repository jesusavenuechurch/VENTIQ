import { Head, router, useForm } from '@inertiajs/react';
import { Download, Smartphone, Star, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface Release {
    id: number;
    version: string;
    current: boolean;
    size: string | null;
    hasApk: boolean;
    playStore: string | null;
    notes: string | null;
    uploaded: string | null;
    download: string | null;
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** Builds of the VENTIQ Scanner app: the current one is what organizers get. */
export default function AppReleases({ releases }: { releases: Release[] }) {
    const form = useForm({ version: '', apk: null as File | null, play_store_url: '', notes: '', is_current: true });
    const [deleting, setDeleting] = useState<Release | null>(null);

    return (
        <AdminLayout title="Scanner app" subtitle="Upload each Android build of the VENTIQ Scanner. Organizers are offered the current one, as an APK and on the Play Store once it's listed.">
            <Head title="Scanner app" />
            <div className="grid gap-6 lg:grid-cols-5">
                <div className="space-y-3 lg:col-span-3">
                    {releases.length === 0 && <p className={cn(card, 'text-[13px] text-gray-400')}>No builds uploaded yet.</p>}
                    {releases.map((r) => (
                        <div key={r.id} className={cn(card, 'flex flex-wrap items-center justify-between gap-3', r.current && 'border-mint')}>
                            <div className="min-w-0">
                                <p className="text-[15px] font-black text-brand">
                                    <Smartphone className="mr-1 inline size-4" />Version {r.version}
                                    {r.current && <span className="ml-2 rounded-full bg-mint px-2 py-0.5 align-middle text-[10px] font-black uppercase tracking-widest text-mint-ink">Current</span>}
                                </p>
                                <p className="text-[11px] text-gray-400">Uploaded {r.uploaded}{r.size && ` · ${r.size}`}{r.playStore && ' · on the Play Store'}{!r.hasApk && ' · no APK'}</p>
                                {r.notes && <p className="mt-1 text-[12px] text-gray-500">{r.notes}</p>}
                            </div>
                            <div className="flex items-center gap-2">
                                {r.download && <a href={r.download} className="inline-flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-brand"><Download className="size-3" />APK</a>}
                                {!r.current && <button type="button" onClick={() => router.put(route('ventiq.app-releases.update', r.id), { is_current: true, play_store_url: r.playStore, notes: r.notes }, { preserveScroll: true })} className="inline-flex items-center gap-1 rounded-full bg-brand px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white"><Star className="size-3" />Make current</button>}
                                <button type="button" aria-label={`Delete version ${r.version}`} onClick={() => setDeleting(r)} className="text-gray-300 hover:text-rose-600"><Trash2 className="size-4" /></button>
                            </div>
                        </div>
                    ))}
                </div>

                <form className={cn(card, 'space-y-4 lg:col-span-2')} onSubmit={(e) => { e.preventDefault(); form.post(route('ventiq.app-releases.store'), { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() }); }}>
                    <p className="text-[15px] font-black text-brand">Upload a build</p>
                    <Field label="Version" htmlFor="version" error={form.errors.version}>
                        <Input id="version" required placeholder="e.g. 1.4.0" className={field} value={form.data.version} onChange={(e) => form.setData('version', e.target.value)} />
                    </Field>
                    <div>
                        <label htmlFor="apk" className="inline-flex cursor-pointer items-center gap-2 rounded-full bg-action px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action-ink">
                            <Upload className="size-3.5" />{form.data.apk ? 'Change APK' : 'Choose APK'}
                        </label>
                        <input id="apk" type="file" accept=".apk,application/vnd.android.package-archive" className="sr-only" onChange={(e) => form.setData('apk', e.target.files?.[0] ?? null)} />
                        <p className="mt-1 truncate text-[11px] text-gray-400">{form.data.apk?.name ?? 'Up to 200 MB'}</p>
                        {form.errors.apk && <p className="text-[11px] font-bold text-rose-500">{form.errors.apk}</p>}
                        {form.progress && <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div className="h-full bg-action" style={{ width: `${form.progress.percentage}%` }} /></div>}
                    </div>
                    <Field label={<>Play Store link <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="play" error={form.errors.play_store_url}>
                        <Input id="play" type="url" placeholder="https://play.google.com/store/apps/details?id=…" className={field} value={form.data.play_store_url} onChange={(e) => form.setData('play_store_url', e.target.value)} />
                    </Field>
                    <Field label={<>What's new <span className="normal-case text-gray-300">(optional)</span></>} htmlFor="notes" error={form.errors.notes}>
                        <Input id="notes" className={field} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                    <label className="flex items-center gap-3 text-[13px] font-bold text-gray-700">
                        <input type="checkbox" checked={form.data.is_current} onChange={(e) => form.setData('is_current', e.target.checked)} className="size-4 accent-action" />
                        Make it the one organizers get
                    </label>
                    <Button type="submit" variant="brand" className="w-full py-3 text-[10px]" disabled={form.processing}>Upload</Button>
                </form>
            </div>

            <ConfirmDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)} title={`Delete version ${deleting?.version}?`} confirmLabel="Delete" onConfirm={() => router.delete(route('ventiq.app-releases.destroy', deleting!.id), { preserveScroll: true, onFinish: () => setDeleting(null) })}>
                Its APK is deleted too.
            </ConfirmDialog>
        </AdminLayout>
    );
}
