import { Head, router } from '@inertiajs/react';
import { Gift, MessageCircle, Ticket as TicketIcon } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, Pill, organizerView, smallButton } from '@/components/admin/cells';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { money } from '@/lib/utils';

interface Row {
    id: number;
    holder: string;
    phone: string | null;
    email: string | null;
    number: string | null;
    code: string | null;
    tier: string | null;
    amount: number;
    status: string;
    payment: string | null;
    comp: boolean;
    delivery: string | null;
    event: { id: number; name: string | null; organizationId: number | null; organization: string | null };
    ticketUrl: string | null;
    created: string | null;
}

const columns: Column<Row>[] = [
    {
        id: 'holder',
        header: 'Holder',
        cell: ({ row: { original: t } }) => (
            <div className="min-w-0">
                <p className="font-black text-brand">{t.holder} {t.comp && <Gift className="inline size-3 text-lilac-ink" aria-label="Complimentary" />}</p>
                <p className="text-[11px] text-gray-400">{t.phone}{t.email && ` · ${t.email}`}</p>
                <p className="font-mono text-[11px] text-gray-400">{t.code}{t.number && ` · ${t.number}`}</p>
            </div>
        ),
    },
    {
        id: 'event',
        header: 'Event',
        cell: ({ row: { original: t } }) => (
            <div className="min-w-0">
                <p className="font-bold text-gray-700">{t.event.name}</p>
                <p className="text-[11px]"><OrgLink id={t.event.organizationId} name={t.event.organization} /></p>
            </div>
        ),
    },
    { id: 'tier', header: 'Type', cell: ({ row }) => <span className="text-gray-600">{row.original.tier} · {money(row.original.amount)}</span> },
    {
        id: 'status',
        header: 'Status',
        cell: ({ row: { original: t } }) => (
            <div className="flex flex-col items-start gap-1">
                <Pill value={t.status} />
                {t.delivery === 'failed' && <span className="text-[10px] font-bold text-rose-600">WhatsApp failed</span>}
            </div>
        ),
    },
    { id: 'created', header: 'Made', meta: { sort: 'created' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.created}</span> },
    {
        id: 'actions',
        header: '',
        meta: { className: 'text-right' },
        cell: ({ row: { original: t } }) => (
            <div className="flex justify-end gap-1.5">
                {t.ticketUrl && (
                    <>
                        <a href={t.ticketUrl} target="_blank" rel="noopener" className={smallButton}><TicketIcon className="size-3" />View</a>
                        <button type="button" onClick={() => router.post(route('ventiq.tickets.resend', t.id), {}, { preserveScroll: true })} className={smallButton}><MessageCircle className="size-3" />Send</button>
                    </>
                )}
                {t.event.organizationId && <a href={organizerView(t.event.organizationId, { id: t.event.id, q: t.code ?? undefined })} className={smallButton}>Manage</a>}
            </div>
        ),
    },
];

/** Every ticket on VENTIQ, for finding someone's. */
export default function Tickets({ rows, state }: { rows: Paginated<Row>; state: TableState }) {
    return (
        <AdminLayout title="Tickets" subtitle="Find anyone's ticket. Marking it paid or cancelling it happens in its event's organizer view (Manage).">
            <Head title="Tickets" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Name, phone, email, ticket number or entry code"
                filters={[
                    { key: 'status', label: 'Status', options: ['active', 'pending', 'checked_in', 'expired', 'cancelled', 'void', 'refunded'].map((v) => ({ value: v, label: v.replace('_', ' ') })) },
                    { key: 'payment', label: 'Payment', options: ['completed', 'partial', 'pending', 'failed'].map((v) => ({ value: v, label: v })) },
                    { key: 'comp', label: 'Complimentary', options: [{ value: 'yes', label: 'Complimentary' }, { value: 'no', label: 'Not complimentary' }] },
                ]}
                empty="No tickets match."
            />
        </AdminLayout>
    );
}
