import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, GraduationCap } from 'lucide-react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Module {
    title: string;
    body: string[];
    questions: { key: string; text: string; options: Record<string, string> }[];
}

interface Props {
    modules: Module[];
    acknowledgments: Record<string, string>;
    passPercent: number;
    cert: { passed: boolean; score: number; total: number; percent: number; attempts: number; completed: string | null } | null;
    referralUrl: string;
}

const card = 'rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm sm:p-8';

/** The four modules, a few questions after each, and four statements to confirm. */
export default function Certification({ modules, acknowledgments, passPercent, cert, referralUrl }: Props) {
    const { flash, errors } = usePage().props;
    const form = useForm({ answers: {} as Record<string, string>, acks: {} as Record<string, boolean> });
    const questions = modules.flatMap((m) => m.questions);
    const answered = questions.filter((q) => form.data.answers[q.key]).length;
    const allAcked = Object.keys(acknowledgments).every((k) => form.data.acks[k]);
    let n = 0;

    return (
        <SiteLayout>
            <Head title="Certification" />
            <div className="mx-auto max-w-3xl space-y-6 px-4 py-8">
                <Link href={route('agent.home')} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand"><ArrowLeft className="size-3" />Back</Link>
                <div>
                    <h1 className="text-2xl font-black tracking-tight text-brand"><GraduationCap className="mr-2 inline size-6" />Agent certification</h1>
                    <p className="mt-1 text-[13px] text-gray-500">Read each module and answer its questions. {passPercent}% passes; you can try as often as you like.</p>
                </div>

                {flash.status && <Alert className="rounded-2xl p-4 text-[12px]">{flash.status}</Alert>}
                {flash.error && <Alert variant="destructive" className="rounded-2xl p-4 text-[12px]">{flash.error}</Alert>}

                {cert?.passed ? (
                    <div className={cn(card, 'text-center')}>
                        <BadgeCheck className="mx-auto size-12 text-mint-ink" />
                        <p className="mt-3 text-xl font-black text-brand">You're certified</p>
                        <p className="text-[13px] text-gray-500">Scored {cert.score} of {cert.total} ({cert.percent}%){cert.completed && ` on ${cert.completed}`}.</p>
                        <code className="mt-4 block truncate rounded-2xl bg-slate-50 px-4 py-3 font-mono text-[13px] text-brand">{referralUrl}</code>
                    </div>
                ) : (
                    <form
                        className="space-y-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            // Each statement goes as its own field (ack1…ack4).
                            form.transform((d) => ({ answers: d.answers, ...d.acks }));
                            form.post(route('agent.certification.submit'), { preserveScroll: false });
                        }}
                    >
                        {cert && <p className="rounded-2xl bg-action-soft p-4 text-[12px] font-bold text-action-ink">Last try: {cert.score} of {cert.total} ({cert.percent}%). {cert.attempts} {cert.attempts === 1 ? 'attempt' : 'attempts'} so far.</p>}

                        {modules.map((m, i) => (
                            <section key={m.title} className={cn(card, 'space-y-5')}>
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-widest text-action">Module {i + 1}</p>
                                    <h2 className="text-lg font-black text-brand">{m.title}</h2>
                                    <div className="mt-2 space-y-2 text-[13px] text-gray-600">{m.body.map((p) => <p key={p}>{p}</p>)}</div>
                                </div>
                                {m.questions.map((q) => {
                                    n += 1;
                                    return (
                                        <fieldset key={q.key} className="space-y-2">
                                            <legend className="mb-2 text-[13px] font-black text-gray-800">{n}. {q.text}</legend>
                                            {Object.entries(q.options).map(([value, label]) => (
                                                <label key={value} className={cn('flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-3 text-[13px]', form.data.answers[q.key] === value ? 'border-brand bg-brand/5' : 'border-gray-100 hover:border-gray-200')}>
                                                    <input type="radio" name={q.key} checked={form.data.answers[q.key] === value} onChange={() => form.setData('answers', { ...form.data.answers, [q.key]: value })} className="accent-brand" />
                                                    <span><strong>{value})</strong> {label}</span>
                                                </label>
                                            ))}
                                        </fieldset>
                                    );
                                })}
                            </section>
                        ))}

                        <section className={cn(card, 'space-y-3')}>
                            <h2 className="text-lg font-black text-brand">Final acknowledgment</h2>
                            <p className="text-[12px] text-gray-500">Confirm all four to submit.</p>
                            {Object.entries(acknowledgments).map(([key, text]) => (
                                <label key={key} className={cn('flex cursor-pointer gap-3 rounded-2xl border-2 p-3 text-[13px]', form.data.acks[key] ? 'border-brand bg-brand/5' : 'border-gray-100')}>
                                    <input type="checkbox" checked={!!form.data.acks[key]} onChange={(e) => form.setData('acks', { ...form.data.acks, [key]: e.target.checked })} className="mt-0.5 size-4 accent-brand" />
                                    {text}
                                </label>
                            ))}
                            {Object.keys(errors).length > 0 && <p className="text-[12px] font-bold text-rose-600">{Object.values(errors)[0]}</p>}
                        </section>

                        <div className="flex flex-wrap items-center gap-4">
                            <Button type="submit" variant="brand" className="px-6 py-4 text-[11px]" disabled={form.processing || answered < questions.length || !allAcked}>Submit and get certified</Button>
                            <span className="text-[11px] font-bold text-gray-400">{answered} of {questions.length} answered</span>
                        </div>
                    </form>
                )}
            </div>
        </SiteLayout>
    );
}
