import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, Pill, smallButton } from '@/components/admin/cells';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { money } from '@/lib/utils';

interface Row {
    id: number;
    organization: { id: number; name: string | null };
    created: string | null;
    trigger: string | null;
    items: number;
    received: number;
    fees: number;
    owed: number;
    status: string;
    settled: string | null;
    method: string | null;
    reference: string | null;
}

interface Batch {
    id: number;
    organization: string | null;
    notes: string | null;
    items: { id: number; attendee: string | null; event: string | null; via: string; received: number; fee: number; owed: number }[];
}

// Open a batch's payments without leaving the list (?batch=).
const open = (id: number | null) => {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('batch', String(id));
    else url.searchParams.delete('batch');
    router.get(url.pathname + url.search, {}, { only: ['batch'], preserveState: true, preserveScroll: true, replace: true });
};

const columns: Column<Row>[] = [
    { id: 'id', header: 'Batch', cell: ({ row }) => <span className="font-mono font-bold text-gray-500">#{row.original.id}</span> },
    { id: 'organization', header: 'Organization', cell: ({ row }) => <OrgLink id={row.original.organization.id} name={row.original.organization.name} /> },
    { id: 'created', header: 'Made', meta: { sort: 'created' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.created}</span> },
    { id: 'items', header: 'Payments', meta: { className: 'text-right' }, cell: ({ row }) => row.original.items },
    { id: 'fees', header: 'VENTIQ fees', meta: { className: 'text-right' }, cell: ({ row }) => <span className="text-lilac-ink">{money(row.original.fees)}</span> },
    { id: 'owed', header: 'To organizer', meta: { sort: 'amount', className: 'text-right' }, cell: ({ row }) => <span className="font-black text-brand">{money(row.original.owed)}</span> },
    {
        id: 'status',
        header: 'Status',
        cell: ({ row: { original: s } }) => (
            <div className="flex flex-col items-start gap-0.5">
                <Pill value={s.status} label={s.status === 'settled' ? 'Paid' : undefined} />
                {s.settled && <span className="text-[10px] text-gray-400">{s.settled} · {s.method?.replace('_', ' ')} {s.reference}</span>}
            </div>
        ),
    },
    { id: 'open', header: '', meta: { className: 'text-right' }, cell: ({ row }) => <button type="button" onClick={() => open(row.original.id)} className={smallButton}>Payments</button> },
];

/** Every payout batch, and the payments in each. */
export default function Payouts({ rows, state, batch }: { rows: Paginated<Row>; state: TableState; batch: Batch | null }) {
    return (
        <AdminLayout title="Payouts" subtitle="Every payout batch to organizers. Make and pay new ones on the Money page.">
            <Head title="Payouts" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Organization or transfer reference"
                filters={[{ key: 'status', label: 'Status', options: [{ value: 'pending', label: 'To pay' }, { value: 'settled', label: 'Paid' }] }]}
                empty="No payouts yet."
            />

            <Dialog open={batch !== null} onOpenChange={(o) => !o && open(null)}>
                <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto rounded-[2rem]">
                    {batch && (
                        <div className="space-y-4 p-7">
                            <DialogTitle className="pr-8 text-lg normal-case not-italic tracking-normal">Batch #{batch.id} · {batch.organization}</DialogTitle>
                            {batch.notes && <p className="text-[12px] text-gray-500">{batch.notes}</p>}
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-[12px]">
                                    <thead className="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                        <tr><th className="py-2">Attendee</th><th className="py-2">Event</th><th className="py-2">Paid via</th><th className="py-2 text-right">Received</th><th className="py-2 text-right">Fee</th><th className="py-2 text-right">To organizer</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {batch.items.map((i) => (
                                            <tr key={i.id}>
                                                <td className="py-2 font-bold text-brand">{i.attendee ?? '—'}</td>
                                                <td className="py-2 text-gray-600">{i.event ?? '—'}</td>
                                                <td className="py-2 font-mono text-[11px] text-gray-400">{i.via || '—'}</td>
                                                <td className="py-2 text-right">{money(i.received)}</td>
                                                <td className="py-2 text-right text-lilac-ink">−{money(i.fee)}</td>
                                                <td className="py-2 text-right font-black">{money(i.owed)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="border-t border-gray-100 font-black">
                                        <tr>
                                            <td className="py-2" colSpan={3}>{batch.items.length} {batch.items.length === 1 ? 'payment' : 'payments'}</td>
                                            <td className="py-2 text-right">{money(batch.items.reduce((a, i) => a + i.received, 0))}</td>
                                            <td className="py-2 text-right text-lilac-ink">−{money(batch.items.reduce((a, i) => a + i.fee, 0))}</td>
                                            <td className="py-2 text-right">{money(batch.items.reduce((a, i) => a + i.owed, 0))}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
