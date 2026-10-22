import { Head, router, useForm } from '@inertiajs/react';
import { Mail, MessageCircle, Phone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { Pill } from '@/components/admin/cells';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

interface Row {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    subject: string | null;
    message: string;
    status: string;
    notes: string | null;
    whatsapp: string | null;
    received: string | null;
}

type Status = { value: string; label: string };

/** Messages from the contact form. */
export default function Inbox({ rows, state, statuses, unread }: { rows: Paginated<Row>; state: TableState; statuses: Status[]; unread: number }) {
    const [deleting, setDeleting] = useState<Row | null>(null);

    const columns: Column<Row>[] = [
        {
            id: 'message',
            header: 'Message',
            cell: ({ row: { original: m } }) => <Message message={m} statuses={statuses} onDelete={() => setDeleting(m)} />,
        },
    ];

    return (
        <AdminLayout title="Inbox" subtitle={unread ? `${unread} new ${unread === 1 ? 'message' : 'messages'} from the contact form.` : 'Messages from the contact form.'}>
            <Head title="Inbox" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Name, phone, email or words in the message"
                filters={[{ key: 'status', label: 'Status', options: statuses }]}
                empty="No messages."
            />
            <ConfirmDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)} title={`Delete ${deleting?.name}'s message?`} confirmLabel="Delete" onConfirm={() => router.delete(route('ventiq.inbox.destroy', deleting!.id), { preserveScroll: true, onFinish: () => setDeleting(null) })} />
        </AdminLayout>
    );
}

function Message({ message: m, statuses, onDelete }: { message: Row; statuses: Status[]; onDelete: () => void }) {
    const form = useForm({ status: m.status, notes: m.notes ?? '' });
    const save = (status = form.data.status) => router.put(route('ventiq.inbox.update', m.id), { status, notes: form.data.notes }, { preserveScroll: true, preserveState: true });

    return (
        <div className="space-y-3 py-1">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-[14px] font-black text-brand">{m.name} {m.subject && <span className="font-bold text-gray-500">· {m.subject}</span>}</p>
                    <p className="text-[11px] text-gray-400">{m.received}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Pill value={m.status} />
                    <NativeSelect aria-label="Status" value={form.data.status} onChange={(e) => { form.setData('status', e.target.value); save(e.target.value); }} className="w-auto rounded-full py-1.5 pr-8 text-[11px]">
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </NativeSelect>
                </div>
            </div>
            <p className="whitespace-pre-line text-[13px] text-gray-700">{m.message}</p>
            <div className="flex flex-wrap items-center gap-2 text-[11px] font-bold">
                {m.whatsapp && <a href={m.whatsapp} target="_blank" rel="noopener" onClick={() => m.status === 'new' && save('replied')} className="inline-flex items-center gap-1 rounded-full bg-[#25D366] px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white"><MessageCircle className="size-3" />Reply on WhatsApp</a>}
                {m.phone && <a href={`tel:${m.phone}`} className="inline-flex items-center gap-1 text-gray-500 hover:text-brand"><Phone className="size-3" />{m.phone}</a>}
                {m.email && <a href={`mailto:${m.email}`} className="inline-flex items-center gap-1 text-gray-500 hover:text-brand"><Mail className="size-3" />{m.email}</a>}
                <button type="button" onClick={onDelete} className="ml-auto inline-flex items-center gap-1 text-gray-300 hover:text-rose-600"><Trash2 className="size-3" />Delete</button>
            </div>
            <Textarea aria-label="Notes" rows={1} placeholder="Notes for the team (saved when you leave the box)" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} onBlur={() => form.data.notes !== (m.notes ?? '') && save()} className="text-[12px]" />
        </div>
    );
}
