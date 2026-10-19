import { Check, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { formatLocal, localDigits } from '@/components/phone-input';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

const SUBJECTS = ['Host an Event (Free Trial)', 'Ticket Support', 'Partnership Inquiry', 'General Support'];

/** Support: a message to VENTIQ's team (stored as a contact inquiry). */
export function SupportDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const [subject, setSubject] = useState('');
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [sent, setSent] = useState(false);

    function reset() {
        setSent(false);
        setName('');
        setPhone('');
        setMessage('');
        setSubject('');
    }

    async function send() {
        if (!name || phone.length < 8 || !message || !subject) {
            setError('Please complete all required fields.');
            return;
        }
        setError(null);
        setSending(true);
        try {
            const res = await fetch(route('contact.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ name, phone: '+266 ' + formatLocal(phone), subject, message }),
            });
            if (res.ok) setSent(true);
            else setError((await res.json()).message || 'Your message could not be sent.');
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setSending(false);
        }
    }

    const field = 'w-full rounded-2xl bg-gray-50 px-5 py-4 text-sm font-bold outline-none focus:ring-2 focus:ring-action/20';
    const label = 'ml-1 text-[10px] font-black uppercase tracking-wider text-brand';

    return (
        <Dialog open={open} onOpenChange={(o) => { onOpenChange(o); if (!o && sent) reset(); }}>
            <DialogContent>
                <div className="p-8">
                    {sent ? (
                        <div className="py-12 text-center">
                            <div className="mx-auto mb-6 flex size-20 items-center justify-center rounded-full bg-green-50 text-green-500 shadow-inner"><Check className="size-7" /></div>
                            <DialogTitle className="tracking-tighter">Message Sent</DialogTitle>
                            <DialogDescription className="mt-2">Our core team will respond shortly.</DialogDescription>
                            <button type="button" onClick={() => { onOpenChange(false); reset(); }} className="mt-8 border-b border-action/20 pb-1 text-[10px] font-black uppercase tracking-[0.3em] text-action">Close</button>
                        </div>
                    ) : (
                        <>
                            <div className="mb-6">
                                <DialogTitle>Support<span className="text-action">.</span></DialogTitle>
                                <DialogDescription>Talk to the VENTIQ team</DialogDescription>
                            </div>
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <label htmlFor="support_subject" className={label}>Inquiry Type</label>
                                    <select id="support_subject" value={subject} onChange={(e) => setSubject(e.target.value)} className={cn(field, 'cursor-pointer text-xs', !subject && 'italic text-gray-400')}>
                                        <option value="" disabled>Select Inquiry Type</option>
                                        {SUBJECTS.map((s) => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                </div>
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="space-y-1">
                                        <label htmlFor="support_name" className={label}>Name</label>
                                        <input id="support_name" value={name} onChange={(e) => setName(e.target.value)} className={field} />
                                    </div>
                                    <div className="space-y-1">
                                        <label htmlFor="support_phone" className={label}>WhatsApp (+266)</label>
                                        <input id="support_phone" type="tel" inputMode="tel" placeholder="5... ...." value={formatLocal(phone)} onChange={(e) => setPhone(localDigits(e.target.value))} className={cn(field, 'font-mono tracking-widest')} />
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <label htmlFor="support_message" className={label}>Message</label>
                                    <textarea id="support_message" rows={3} value={message} onChange={(e) => setMessage(e.target.value)} className={cn(field, 'resize-none font-medium')} />
                                </div>
                                {error && <p className="ml-1 text-[10px] font-bold uppercase tracking-tighter text-red-500">{error}</p>}
                                <button type="button" onClick={send} disabled={sending} className="flex w-full items-center justify-center rounded-2xl bg-brand py-5 text-[10px] font-black uppercase tracking-[0.3em] text-white shadow-xl transition-all hover:bg-action">
                                    {sending ? <Loader2 className="size-5 animate-spin" /> : 'Send Inquiry'}
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
