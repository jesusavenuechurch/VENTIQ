import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/alert';

/**
 * Attendee pages: the slim VENTIQ header (V. | who it's for | what this
 * page is), the status flash, and a narrow column that reads well on a phone.
 */
export default function GuestLayout({
    label,
    context,
    children,
}: {
    /** The orange word on the right of the header, e.g. "Payment". */
    label: string;
    /** Shown next to the V., e.g. the organizer's name. */
    context?: string;
    children: React.ReactNode;
}) {
    const { flash } = usePage().props;

    return (
        <div className="min-h-screen bg-background text-brand">
            <header className="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur-md">
                <div className="mx-auto flex h-14 max-w-3xl items-center justify-between px-6">
                    <div className="flex items-center gap-3">
                        <Link href="/" className="text-xl font-black tracking-tighter hover:text-action">V.</Link>
                        {context && (
                            <>
                                <div className="h-4 w-px bg-gray-200" />
                                <span className="max-w-[160px] truncate text-[9px] font-bold uppercase tracking-widest text-gray-400">{context}</span>
                            </>
                        )}
                    </div>
                    <span className="text-[9px] font-black uppercase tracking-[0.2em] text-action">{label}</span>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-8 pb-20 lg:px-6">
                {flash.status && <Alert className="mb-6">{flash.status}</Alert>}
                {children}
            </main>
        </div>
    );
}
