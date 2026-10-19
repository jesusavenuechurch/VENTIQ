import { Head, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Field } from '@/components/field';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/** Find my ticket: the phone registered with, and the ticket number or entry code. */
export default function Find() {
    const form = useForm({ phone: '', ticket_number: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(route('installment.find'));
    }

    return (
        <GuestLayout label="Find my ticket">
            <Head title="Find my ticket" />

            <Card>
                <CardHeader className="text-center">
                    <div className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl bg-slate-100">
                        <Search className="size-7 text-brand" />
                    </div>
                    <CardTitle>Find your ticket</CardTitle>
                    <CardDescription>
                        Enter the phone you registered with to open your ticket: pay, pay the rest, or show it at the door.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    {form.errors.ticket_number && !form.errors.phone && form.errors.ticket_number.startsWith('No ticket') && (
                        <Alert variant="destructive" className="mb-6">{form.errors.ticket_number}</Alert>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <Field label="Phone number" htmlFor="phone" error={form.errors.phone} hint="The number you registered with">
                            <div className="flex items-center gap-2">
                                <span className="rounded-2xl bg-slate-100 px-4 py-4 font-bold text-gray-500">+266</span>
                                <Input
                                    id="phone"
                                    type="tel"
                                    inputMode="tel"
                                    required
                                    placeholder="5949 4756"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    aria-invalid={!!form.errors.phone}
                                />
                            </div>
                        </Field>

                        <Field
                            label="Ticket number or entry code"
                            htmlFor="ticket_number"
                            error={form.errors.ticket_number?.startsWith('No ticket') ? undefined : form.errors.ticket_number}
                            hint="In your registration email or WhatsApp message"
                        >
                            <Input
                                id="ticket_number"
                                required
                                autoCapitalize="characters"
                                placeholder="e.g. VQ-X82L or TKT-6-AB12CD34"
                                value={form.data.ticket_number}
                                onChange={(e) => form.setData('ticket_number', e.target.value)}
                                aria-invalid={!!form.errors.ticket_number}
                            />
                        </Field>

                        <Button type="submit" className="w-full" disabled={form.processing}>
                            <Search /> Find my ticket
                        </Button>
                    </form>

                    <p className="mt-8 border-t border-gray-100 pt-6 text-center text-[12px] font-bold text-gray-400">
                        Can't find either? Ask the event's organizer to send your ticket again.
                    </p>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
