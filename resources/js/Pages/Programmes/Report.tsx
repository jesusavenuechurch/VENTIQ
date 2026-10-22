import { router, usePoll } from '@inertiajs/react';
import { LoaderCircle, RotateCw, Sparkles } from 'lucide-react';
import { useEffect } from 'react';
import SessionsLayout, { primaryButton, quietButton } from '@/Layouts/SessionsLayout';

interface Props {
    programme: { id: number; name: string; report: string | null; generating: boolean; failed: boolean };
    reported: number;
    total: number;
}

/** One report for the whole programme, put together from its sessions' reports. */
export default function Report({ programme, reported, total }: Props) {
    const { start, stop } = usePoll(3000, { only: ['programme'] }, { autoStart: programme.generating });
    useEffect(() => (programme.generating ? start() : stop()), [programme.generating, start, stop]);
    const generate = () => router.post(route('programmes.report.generate', programme.id), {}, { preserveScroll: true });

    return (
        <SessionsLayout
            title={`${programme.name}: report`}
            subtitle={`${reported} of ${total} ${total === 1 ? 'session' : 'sessions'} reported`}
            crumbs={[[programme.name, route('programmes.show', programme.id)], ['Report']]}
            width="max-w-4xl"
        >
            {reported === 0 ? (
                <p className="rounded-[2rem] border border-dashed border-gray-200 py-16 text-center text-sm font-bold text-gray-400">No session has a report yet. This fills in as each one is reported.</p>
            ) : programme.generating ? (
                <div className="rounded-[2rem] border border-dashed border-gray-200 py-16 text-center">
                    <LoaderCircle className="mx-auto mb-5 size-8 animate-spin text-action" />
                    <p className="text-sm font-bold text-gray-500">Putting the programme report together…</p>
                </div>
            ) : programme.failed && !programme.report ? (
                <div className="rounded-[2rem] border border-dashed border-rose-200 bg-rose-50/40 py-16 text-center">
                    <p className="text-sm font-bold text-rose-600">The report couldn't be written</p>
                    <p className="mt-1 text-[12px] text-gray-500">Nothing was lost: each session's report is still there.</p>
                    <button type="button" onClick={generate} className={`${quietButton} mt-6`}><RotateCw className="size-3.5" />Try again</button>
                </div>
            ) : programme.report ? (
                <>
                    <div className="whitespace-pre-wrap rounded-2xl border border-gray-100 bg-white p-6 text-[13px] font-medium leading-relaxed text-gray-700 md:p-8">{programme.report}</div>
                    <button type="button" onClick={generate} className={`${quietButton} mt-4`}><RotateCw className="size-3.5" />Write it again with the latest sessions</button>
                </>
            ) : (
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white p-6">
                    <div>
                        <p className="text-[13px] font-black text-brand">Ready to write</p>
                        <p className="text-[12px] text-gray-500">{reported} session {reported === 1 ? 'report' : 'reports'} to put together into one.</p>
                    </div>
                    <button type="button" onClick={generate} className={primaryButton}><Sparkles className="size-4" />Write the report</button>
                </div>
            )}
        </SessionsLayout>
    );
}
