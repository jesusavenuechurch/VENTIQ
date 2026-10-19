import { Head } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Lock, Mail } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { EventData } from '@/types/event';

/** Registration is closed: why, and where to go instead. */
export default function RegistrationClosed({ event, closedReason, longDate, browseUrl }: { event: EventData; closedReason: string; longDate: string | null; browseUrl: string }) {
    return (
        <GuestLayout context={event.organization.name} label="Registration">
            <Head title={`Registration Closed - ${event.name}`} />

            <Card>
                <CardContent className="text-center">
                    <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-action-soft">
                        <Lock className="size-7 text-action-ink" />
                    </div>
                    <h2 className="text-2xl font-black uppercase tracking-tight text-gray-900">Registration Closed</h2>
                    <p className="mt-3 text-sm font-bold text-gray-500">{closedReason || 'Sorry, registration for this event is no longer available.'}</p>

                    <div className="mx-auto mt-8 max-w-md rounded-2xl bg-slate-50 p-6 text-left">
                        <h3 className="font-black text-brand">{event.name}</h3>
                        {longDate && (
                            <p className="mt-2 flex items-center gap-2 text-[13px] font-medium text-gray-500">
                                <CalendarDays className="size-4 text-action" />Event date: {longDate}
                            </p>
                        )}
                    </div>

                    {event.organization.contactEmail && (
                        <div className="mt-8 border-t border-gray-100 pt-6">
                            <p className="text-[13px] font-medium text-gray-500">Have questions or need assistance?</p>
                            <a href={`mailto:${event.organization.contactEmail}`} className="mt-2 inline-flex items-center gap-2 text-[13px] font-black text-brand hover:text-action">
                                <Mail className="size-4" />Contact {event.organization.name}
                            </a>
                        </div>
                    )}

                    <Button asChild variant="brand" className="mt-8">
                        <a href={browseUrl}><ArrowLeft />View Other Events</a>
                    </Button>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
