import { Head, useForm, usePage } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Field } from '@/components/field';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PhoneInput, localDigits } from '@/components/phone-input';
import { cn } from '@/lib/utils';

const card = 'space-y-4 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';
const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';

/** Your name, how your reminders reach you, and your password. */
export default function Account({ account, reminders, needsCurrent }: { account: { name: string; email: string; google: boolean }; reminders: { via: 'email' | 'whatsapp'; phone: string }; needsCurrent: boolean }) {
    const { flash } = usePage().props;
    const name = useForm({ name: account.name });
    const remind = useForm({ via: reminders.via, phone: localDigits(reminders.phone) });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <SiteLayout>
            <Head title="My account" />
            <div className="mx-auto max-w-xl space-y-6 px-4 py-8">
                <div>
                    <p className="mb-1 text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Ventiq</p>
                    <h1 className="text-2xl font-black tracking-tight text-brand">My account</h1>
                    <p className="mt-1 text-[13px] font-medium text-gray-500">{account.email}{account.google && ' · signs in with Google'}</p>
                </div>
                {flash.status && <Alert className="rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}

                <form className={card} onSubmit={(e) => { e.preventDefault(); name.put(route('account.update'), { preserveScroll: true }); }}>
                    <Field label="Your name" htmlFor="name" error={name.errors.name}>
                        <Input id="name" required className={field} value={name.data.name} onChange={(e) => name.setData('name', e.target.value)} />
                    </Field>
                    <Button type="submit" variant="brand" className="px-6 py-3 text-[11px]" disabled={name.processing}>Save name</Button>
                </form>

                <form className={card} onSubmit={(e) => { e.preventDefault(); remind.put(route('account.reminders'), { preserveScroll: true }); }}>
                    <div>
                        <p className="text-[15px] font-black text-brand">Action point reminders</p>
                        <p className="mt-1 text-[12px] text-gray-500">When an action point of yours from a session is due, we remind you before the date.</p>
                    </div>
                    <div role="radiogroup" aria-label="Send my reminders" className="grid grid-cols-2 gap-2">
                        {([['whatsapp', 'On WhatsApp', 'Quick to see on your phone'], ['email', 'By email', `To ${account.email}`]] as const).map(([value, label, hint]) => (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={remind.data.via === value}
                                onClick={() => remind.setData('via', value)}
                                className={cn('rounded-2xl border-2 px-4 py-3 text-left transition-colors', remind.data.via === value ? 'border-brand bg-brand/5' : 'border-gray-100 hover:border-gray-200')}
                            >
                                <span className="block text-[13px] font-black text-brand">{label}</span>
                                <span className="block truncate text-[11px] text-gray-500">{hint}</span>
                            </button>
                        ))}
                    </div>
                    {remind.data.via === 'whatsapp' && (
                        <Field label="Your WhatsApp number" htmlFor="reminder_phone" error={remind.errors.phone} hint="If a WhatsApp message can't be sent, the reminder comes by email instead.">
                            <PhoneInput id="reminder_phone" required value={remind.data.phone} onChange={(v) => remind.setData('phone', v)} invalid={!!remind.errors.phone} />
                        </Field>
                    )}
                    <Button type="submit" variant="brand" className="px-6 py-3 text-[11px]" disabled={remind.processing}>Save reminders</Button>
                </form>

                <form className={card} onSubmit={(e) => { e.preventDefault(); password.put(route('account.password'), { preserveScroll: true, onSuccess: () => password.reset() }); }}>
                    <div>
                        <p className="text-[15px] font-black text-brand">{needsCurrent ? 'Change your password' : account.google ? 'Add a password' : 'Set a new password'}</p>
                        <p className="mt-1 text-[12px] text-gray-500">
                            {needsCurrent
                                ? 'Forgot it? Sign out and choose "Email me a code instead"; you can set a new one here after.'
                                : account.google
                                  ? 'You sign in with Google or an emailed code. A password lets you sign in on any device without either.'
                                  : 'You signed in with an emailed code, so you can set a new password without the old one.'}
                        </p>
                    </div>
                    {needsCurrent && (
                        <Field label="Current password" htmlFor="current_password" error={password.errors.current_password}>
                            <Input id="current_password" type="password" required autoComplete="current-password" className={field} value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} />
                        </Field>
                    )}
                    <Field label="New password" htmlFor="password" error={password.errors.password} hint="At least 10 characters, with letters and numbers.">
                        <Input id="password" type="password" required minLength={10} autoComplete="new-password" className={field} value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} />
                    </Field>
                    <Field label="New password again" htmlFor="password_confirmation">
                        <Input id="password_confirmation" type="password" required autoComplete="new-password" className={field} value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} />
                    </Field>
                    <Button type="submit" variant="brand" className="px-6 py-3 text-[11px]" disabled={password.processing}>Save password</Button>
                </form>
            </div>
        </SiteLayout>
    );
}
