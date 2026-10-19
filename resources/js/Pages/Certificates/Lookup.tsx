import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/** Check a certificate by the number printed on it (the QR code goes straight to it). */
export default function Lookup({ number: initial, error }: { number: string; error: string | null }) {
    const [number, setNumber] = useState(initial);
    return (
        <GuestLayout label="Certificates">
            <Head title="Verify a certificate" />
            <Card>
                <CardContent className="space-y-5 text-center">
                    <div>
                        <h1 className="text-2xl font-black uppercase tracking-tight text-gray-900">Verify a certificate</h1>
                        <p className="mt-2 text-sm font-bold text-gray-500">Enter the certificate number printed on the document.</p>
                    </div>
                    {error && <p role="alert" className="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">{error}</p>}
                    <form onSubmit={(e) => { e.preventDefault(); router.get(route('certificates.lookup'), { number: number.trim() }); }} className="space-y-3">
                        <Input aria-label="Certificate number" required autoFocus placeholder="VQ-2026-000184" value={number} onChange={(e) => setNumber(e.target.value.toUpperCase())} className="rounded-2xl px-5 py-4 text-center font-mono font-bold" />
                        <Button type="submit" variant="brand" className="w-full py-4 text-[10px]">Verify</Button>
                    </form>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
