import { Head } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';
import { EventCard, type EventCardData } from '@/components/site/event-card';

interface Props {
    organization: { name: string; tagline: string | null; logo: string | null; contact_email: string | null };
    events: EventCardData[];
}

/** One organization's upcoming listed events. */
export default function Organization({ organization, events }: Props) {
    return (
        <SiteLayout>
            <Head title={`Events | ${organization.name}`} />
            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
                <div className="mb-10 flex items-center gap-4">
                    {organization.logo && <img src={organization.logo} alt="" className="size-16 rounded-2xl object-cover" />}
                    <div>
                        <h1 className="text-3xl font-black uppercase italic tracking-tight text-brand">{organization.name}</h1>
                        {organization.tagline && <p className="text-sm font-medium text-gray-500">{organization.tagline}</p>}
                    </div>
                </div>

                {events.length > 0 ? (
                    <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                        {events.map((e) => <EventCard key={e.id} event={e} />)}
                    </div>
                ) : (
                    <div className="rounded-[2.5rem] border border-dashed border-gray-200 bg-white p-16 text-center shadow-sm">
                        <h3 className="mb-2 text-xl font-black uppercase italic text-brand">No upcoming events</h3>
                        <p className="text-sm font-medium text-gray-400">Check back soon.</p>
                    </div>
                )}

                {organization.contact_email && (
                    <p className="mt-12 text-center text-[12px] text-gray-500">
                        Questions? <a href={`mailto:${organization.contact_email}`} className="font-bold text-brand hover:text-action">{organization.contact_email}</a>
                    </p>
                )}
            </div>
        </SiteLayout>
    );
}
