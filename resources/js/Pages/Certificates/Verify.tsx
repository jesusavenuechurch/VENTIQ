import { Head } from '@inertiajs/react';
import { BadgeCheck, Download } from 'lucide-react';
import GuestLayout from '@/Layouts/GuestLayout';
import { Card, CardContent } from '@/components/ui/card';

interface Certificate {
    holder: string;
    programme: string;
    organization: string;
    issued: string;
    number: string;
    linkedIn: string | null;
    download: string;
}

/** A certificate, checked: who it was awarded to, for what, by whom. */
export default function Verify({ certificate: c }: { certificate: Certificate }) {
    return (
        <GuestLayout label="Certificates" context={c.organization}>
            <Head title={`Certificate · ${c.holder}`} />
            <Card>
                <CardContent className="space-y-6 text-center">
                    <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-mint"><BadgeCheck className="size-8 text-mint-ink" /></div>
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest text-mint-ink">Verified certificate</p>
                        <p className="mt-4 text-[11px] font-bold uppercase tracking-widest text-gray-400">Awarded to</p>
                        <h1 className="text-3xl font-black tracking-tight text-brand">{c.holder}</h1>
                        <p className="mt-3 text-[14px] text-gray-600">For taking part in <strong>{c.programme}</strong>, hosted by <strong>{c.organization}</strong>.</p>
                        <p className="mt-3 text-[12px] text-gray-400">Issued {c.issued} · No. <span className="font-mono">{c.number}</span></p>
                    </div>
                    <div className="space-y-2">
                        {c.linkedIn && <a href={c.linkedIn} target="_blank" rel="noopener" className="block w-full rounded-2xl bg-[#0A66C2] py-4 text-[10px] font-black uppercase tracking-[0.3em] text-white hover:bg-[#08508f]">Add to LinkedIn profile</a>}
                        <a href={c.download} className="flex w-full items-center justify-center gap-2 rounded-2xl bg-gray-100 py-4 text-[10px] font-black uppercase tracking-[0.3em] text-brand hover:bg-gray-200"><Download className="size-3.5" />Download PDF</a>
                    </div>
                </CardContent>
            </Card>
        </GuestLayout>
    );
}
