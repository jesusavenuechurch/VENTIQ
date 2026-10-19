import { Head, useForm } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { Field } from '@/components/field';
import { LogoField } from '@/components/organizer/logo-field';
import { PhoneInput } from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

interface Organization {
    name: string;
    tagline: string | null;
    phone: string;
    contactEmail: string | null;
    website: string | null;
    description: string | null;
    logoUrl: string | null;
}

const field = 'rounded-2xl px-4 py-3 text-[14px] font-semibold disabled:opacity-70';
const optional = <span className="normal-case text-gray-300">(optional)</span>;

/** Who's hosting: the name, logo and contact details attendees see. */
export default function Organization({ organization, canEdit, eventsPage }: { organization: Organization; canEdit: boolean; eventsPage: { url: string; fixed: boolean } | null }) {
    const form = useForm<{ _method: 'put'; name: string; tagline: string; phone: string; contact_email: string; website: string; description: string; logo: File | null; remove_logo: boolean }>({
        _method: 'put',
        name: organization.name,
        tagline: organization.tagline ?? '',
        phone: organization.phone,
        contact_email: organization.contactEmail ?? '',
        website: organization.website ?? '',
        description: organization.description ?? '',
        logo: null,
        remove_logo: false,
    });
    function submit(e: React.FormEvent) {
        e.preventDefault();
        // A file goes as a form post; _method makes it the update.
        form.post(route('organizer.organization.update'), { forceFormData: true, preserveScroll: true });
    }

    return (
        <OrganizerLayout title="Settings" settings subtitle="Who's hosting: the name, logo and contact details attendees see on your events and tickets.">
            <Head title="Organization" />
            <div className="max-w-3xl">
                {!canEdit && (
                    <div className="mb-6 rounded-2xl border border-slate-100 bg-slate-50 p-4 text-[12px] font-medium text-gray-500">
                        You can see your organization's details. Only an admin on your team can change them.
                    </div>
                )}

                <form onSubmit={submit} className="rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                    <fieldset disabled={!canEdit} className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <LogoField
                                current={organization.logoUrl}
                                file={form.data.logo}
                                remove={form.data.remove_logo}
                                onFile={(logo) => form.setData((d) => ({ ...d, logo, remove_logo: false }))}
                                onRemove={(remove) => form.setData('remove_logo', remove)}
                                error={form.errors.logo}
                                editable={canEdit}
                            />
                        </div>

                        <Field className="md:col-span-2" label="Organization name" htmlFor="name" error={form.errors.name}>
                            <Input id="name" required maxLength={255} className={field} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field className="md:col-span-2" label={<>Tagline {optional}</>} htmlFor="tagline" error={form.errors.tagline}>
                            <Input id="tagline" maxLength={120} placeholder="e.g. Bringing Maseru's young leaders together" className={field} value={form.data.tagline} onChange={(e) => form.setData('tagline', e.target.value)} />
                        </Field>
                        <Field label="Phone number" htmlFor="phone" error={form.errors.phone} hint="We message this number when someone pays you.">
                            <PhoneInput id="phone" required value={form.data.phone} onChange={(v) => form.setData('phone', v)} invalid={!!form.errors.phone} />
                        </Field>
                        <Field label={<>Contact email for attendees {optional}</>} htmlFor="contact_email" error={form.errors.contact_email}>
                            <Input id="contact_email" type="email" maxLength={255} className={field} value={form.data.contact_email} onChange={(e) => form.setData('contact_email', e.target.value)} />
                        </Field>
                        <Field className="md:col-span-2" label={<>Website or social page {optional}</>} htmlFor="website" error={form.errors.website}>
                            <Input id="website" type="url" maxLength={255} placeholder="https://facebook.com/yourpage" className={field} value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} />
                        </Field>
                        <Field className="md:col-span-2" label={<>About your organization {optional}</>} htmlFor="description" error={form.errors.description}>
                            <Textarea id="description" rows={4} maxLength={1000} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        </Field>

                        {eventsPage && (
                            <div className="rounded-2xl bg-slate-50 p-4 text-[12px] text-gray-500 md:col-span-2">
                                <p className="font-black text-gray-600"><Link2 className="mr-1 inline size-3.5" />Your events page</p>
                                <a href={eventsPage.url} target="_blank" rel="noopener" className="break-all font-mono text-brand hover:underline">{eventsPage.url}</a>
                                <p className="mt-1">
                                    {eventsPage.fixed
                                        ? "This address stays the same if you rename the organization, so posters, QR codes and links you've shared keep working."
                                        : "Until you create your first event, this address follows your organization's name. After that it stays fixed."}
                                </p>
                            </div>
                        )}

                        {canEdit && (
                            <div className="md:col-span-2">
                                <Button type="submit" variant="brand" className="w-full py-4 text-[11px] sm:w-auto sm:px-8" disabled={form.processing}>Save changes</Button>
                            </div>
                        )}
                    </fieldset>
                </form>
            </div>
        </OrganizerLayout>
    );
}
