import { Head } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';

interface Pass {
    id: number;
    title: string;
    type: string;
    date: string;
    expected: number | null;
    location: string | null;
    organization: string;
    code: string | null;
    joinUrl: string;
}

/** The check-in pass to show on a screen or print: scan the QR, or type the code. */
export default function CheckinPass({ pass }: { pass: Pass }) {
    const qr = route('sessions.checkin.qr', pass.id);
    const joinHost = pass.joinUrl.replace(/^https?:\/\//, '');

    return (
        <div className="flex min-h-dvh flex-col items-center justify-center bg-black px-4 py-10">
            <Head title={`Check-in: ${pass.title}`} />
            <div className="mb-5 flex w-full max-w-3xl items-center justify-between text-white/50">
                <span className="text-[10px] font-black uppercase tracking-[0.3em]">Ventiq · Session check-in</span>
                <span className="font-mono text-[11px]">#{pass.id}</span>
            </div>

            <div className="flex w-full max-w-3xl flex-col overflow-hidden rounded-[2.5rem] bg-white shadow-[0_40px_80px_-20px_rgba(0,0,0,0.6)] md:flex-row">
                <div className="flex flex-1 flex-col justify-between gap-8 p-8 md:p-10">
                    <p className="text-2xl font-black italic tracking-tighter text-slate-900">VENTIQ</p>
                    <div>
                        <span className="inline-block rounded-full bg-[#D4AF37] px-3 py-1 text-[9px] font-black uppercase tracking-widest text-white">{pass.type}</span>
                        <h1 className="mt-3 text-3xl font-black uppercase leading-[0.95] tracking-tight text-slate-900 md:text-4xl">{pass.title}</h1>
                    </div>
                    <div className="grid grid-cols-2 gap-5">
                        <Item label="Date" value={pass.date} />
                        {pass.expected && <Item label="Expected" value={`${pass.expected} people`} />}
                        {pass.location && <div className="col-span-2"><Item label="Location" value={pass.location} /></div>}
                    </div>
                    <p className="text-[11px] font-bold uppercase tracking-widest text-slate-400">{pass.organization}</p>
                </div>

                <div className="relative flex flex-col items-center justify-center gap-4 border-t-2 border-dashed border-slate-200 bg-slate-50 p-8 md:w-72 md:border-l-2 md:border-t-0">
                    <img src={qr} alt="QR code to check in" className="size-48 rounded-2xl bg-white p-2 shadow-sm" />
                    <div className="text-center">
                        <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">Check yourself in</p>
                        <p className="text-lg font-black uppercase text-slate-900">Scan to register</p>
                    </div>
                    {pass.code && (
                        <div className="w-full rounded-2xl bg-white p-4 text-center">
                            <p className="text-[9px] font-black uppercase tracking-widest text-slate-400">Can't scan? Go to {joinHost}</p>
                            <p className="mt-1 font-mono text-2xl font-black tracking-[0.3em] text-slate-900">{pass.code}</p>
                        </div>
                    )}
                </div>
            </div>

            <div className="mt-6 flex gap-3">
                <a href={qr} download className="inline-flex items-center gap-2 rounded-2xl bg-white/10 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-white/20"><Download className="size-4" />QR image</a>
                <a href={route('sessions.checkin.pass.pdf', pass.id)} className="inline-flex items-center gap-2 rounded-2xl bg-white px-5 py-3 text-[10px] font-black uppercase tracking-widest text-slate-900 hover:bg-white/90"><FileText className="size-4" />PDF pass</a>
            </div>
        </div>
    );
}

function Item({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-[9px] font-black uppercase tracking-widest text-slate-400">{label}</p>
            <p className="text-[13px] font-black uppercase text-slate-900">{value}</p>
        </div>
    );
}
