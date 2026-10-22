import { Head, useForm, usePage } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Field } from '@/components/field';
import { LogoField } from '@/components/organizer/logo-field';
import { PhoneInput } from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { SharedProps } from '@/types';

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold';
const optional = <span className="normal-case text-gray-300">(optional)</span>;

/** The organizer's first stop when sign-up didn't ask about their organization. */
export default function Setup({ organization }: { organization: { name: string; phone: string; contactEmail: string | null; description: string | null } }) {
    const { auth } = usePage<SharedProps>().props;
    const form = useForm<{ name: string; phone: string; contact_email: string; description: string; logo: File | null }>({
        name: organization.name,
        phone: organization.phone,
        contact_email: organization.contactEmail ?? '',
        description: organization.description ?? '',
        logo: null,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('organizer.setup.store'), { forceFormData: true });
    }

    return (
        <SiteLayout>
            <Head title="Set up your organization" />
            <div className="mx-auto max-w-xl px-4 py-12">
                <p className="mb-1 text-[10px] font-black uppercase tracking-[0.3em] text-gray-300">Welcome to Ventiq</p>
                <h1 className="text-2xl font-black tracking-tight text-brand">Hi {auth.user?.firstName}, tell us who's hosting</h1>
                <p className="mt-2 text-[13px] font-medium text-gray-500">Attendees see this name on your events and tickets. We'll use the phone number to tell you when someone pays.</p>

                <form onSubmit={submit} className="mt-8 space-y-5 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                    <Field label="Organization name" htmlFor="name" error={form.errors.name} hint="We started with your own name; change it if you host as a church, company or group.">
                        <Input id="name" required maxLength={255} placeholder="e.g. Maseru Youth Network" className={field} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="Phone number" htmlFor="phone" error={form.errors.phone}>
                        <PhoneInput id="phone" required value={form.data.phone} onChange={(v) => form.setData('phone', v)} invalid={!!form.errors.phone} />
                    </Field>
                    <Field label={<>Contact email for attendees {optional}</>} htmlFor="contact_email" error={form.errors.contact_email}>
                        <Input id="contact_email" type="email" maxLength={255} className={field} value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} />
                    </Field>
                    <Field label={<>About your organization {optional}</>} htmlFor="description" error={form.errors.description}>
                        <Textarea id="description" rows={3} maxLength={1000} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </Field>
                    <LogoField
                        label={<>Logo <span className="normal-case text-gray-300">(optional, shown on reports)</span></>}
                        current={null}
                        file={form.data.logo}
                        onFile={(logo) => form.setData('logo', logo)}
                        error={form.errors.logo}
                    />
                    <Button type="submit" variant="brand" className="w-full py-4 text-[11px]" disabled={form.processing}>Continue</Button>
                </form>
            </div>
        </SiteLayout>
    );
}
