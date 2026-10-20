import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import SessionsLayout, { field, fieldLabel } from '@/Layouts/SessionsLayout';

/** A new programme: just the shell; sessions are added to it after. */
export default function Create({ today }: { today: string }) {
    const form = useForm({ name: '', start_date: today, end_date: today, venue: '', certificates_enabled: false, signature_capture_enabled: false });
    const toggles: ['certificates_enabled' | 'signature_capture_enabled', string, string][] = [
        ['certificates_enabled', 'Offer certificates', "You issue them yourself when you're ready. Nothing goes out on its own."],
        ['signature_capture_enabled', 'Signatures at check-in', 'Door staff using the scanner app collect a signature, role, institution and district from each person.'],
    ];

    return (
        <SessionsLayout title="New programme" subtitle="Just the outline: you add its sessions next." crumbs={[['Programmes', route('programmes.index')], ['New programme']]} width="max-w-2xl">
            <form className="space-y-6" onSubmit={(e) => { e.preventDefault(); form.post(route('programmes.store')); }}>
                <div>
                    <label htmlFor="name" className={fieldLabel}>Programme name</label>
                    <input id="name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="e.g. Customer Service Training" className={field} />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="start" className={fieldLabel}>Starts</label>
                        <input id="start" type="date" required value={form.data.start_date} onChange={(e) => form.setData((d) => ({ ...d, start_date: e.target.value, end_date: d.end_date < e.target.value ? e.target.value : d.end_date }))} className={field} />
                    </div>
                    <div>
                        <label htmlFor="end" className={fieldLabel}>Ends</label>
                        <input id="end" type="date" min={form.data.start_date} value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} className={field} />
                        {form.errors.end_date && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-600">{form.errors.end_date}</p>}
                    </div>
                </div>
                <div>
                    <label htmlFor="venue" className={fieldLabel}>Venue (optional)</label>
                    <input id="venue" value={form.data.venue} onChange={(e) => form.setData('venue', e.target.value)} placeholder="e.g. Maseru Convention Centre" className={field} />
                </div>
                {toggles.map(([key, title, text]) => (
                    <label key={key} className="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-5 py-4">
                        <span>
                            <span className="block text-[11px] font-black uppercase tracking-wide text-brand">{title}</span>
                            <span className="mt-0.5 block text-[11px] font-medium text-gray-400">{text}</span>
                        </span>
                        <input type="checkbox" checked={form.data[key]} onChange={(e) => form.setData(key, e.target.checked)} className="size-5 accent-brand" />
                    </label>
                ))}
                <button disabled={form.processing} className="flex w-full items-center justify-center rounded-2xl bg-brand py-5 text-[11px] font-black uppercase tracking-[0.3em] text-white shadow-xl transition-all hover:bg-action disabled:opacity-50">
                    {form.processing ? <LoaderCircle className="size-5 animate-spin" /> : 'Create programme'}
                </button>
            </form>
        </SessionsLayout>
    );
}
