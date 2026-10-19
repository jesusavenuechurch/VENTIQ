import { Head } from '@inertiajs/react';
import { CircleCheck, RotateCcw, TriangleAlert } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { EventData } from '@/types/event';

/** A registration that didn't go through: what went wrong, and try again. */
export default function RegistrationError({ event, error, retryUrl }: { event: EventData; error: string; retryUrl: string }) {
    const tips = [
        'Make sure all required fields are filled correctly',
        'Check that your email address is valid',
        'Ensure your phone number is 8 digits (without +266)',
        'Check that the ticket type is still available',
    ];

    return (
        <GuestLayout context={event.organization.name} label="Registration">
            <Head title="Registration Error" />

            <Card>
                <CardContent className="text-center">
                    <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-rose-50">
                        <TriangleAlert className="size-7 text-rose-500" />
                    </div>
                    <h2 className="text-2xl font-black uppercase tracking-tight text-gray-900">Registration Failed</h2>

                    <Alert variant="destructive" className="mt-6 text-left">
                        <p className="mb-1 text-[10px] font-black uppercase tracking-widest">What went wrong</p>
                        <p>{error || 'An unexpected error occurred while processing your registration. Please try again.'}</p>
                    </Alert>

                    <div className="mt-6 rounded-3xl bg-slate-50 p-6 text-left">
                        <p className="mb-3 text-[10px] font-black uppercase tracking-widest text-gray-400">Common issues</p>
                        <ul className="space-y-2 text-[13px] font-medium text-gray-600">
                            {tips.map((tip) => (
                                <li key={tip} className="flex items-start gap-2"><CircleCheck className="mt-0.5 size-4 shrink-0 text-mint-ink" />{tip}</li>
                            ))}
                        </ul>
                    </div>

                    <Button asChild className="mt-8">
                        <a href={retryUrl}><RotateCcw />Try Again</a>
                    </Button>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
