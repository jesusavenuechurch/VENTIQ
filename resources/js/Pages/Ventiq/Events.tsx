import { Head, router } from '@inertiajs/react';
import { ExternalLink, Gift } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, Pill, organizerView, smallButton } from '@/components/admin/cells';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';

interface Row {
    id: number;
    name: string;
    organization: { id: number; name: string | null };
    date: string | null;
    status: string;
    mode: string | null;
    public: boolean;
    sponsored: boolean;
    tickets: number;
    tiers: number;
    url: string | null;
}

const columns: Column<Row>[] = [
    {
        id: 'name',
        header: 'Event',
        meta: { sort: 'name' },
        cell: ({ row: { original: e } }) => (
            <a href={organizerView(e.organization.id, { id: e.id })} className="block min-w-0">
                <span className="block font-black text-brand hover:text-action">{e.name}</span>
                <span className="block text-[11px] text-gray-400">{e.mode === 'free' ? 'Free' : 'Paid'}{!e.public && ' · not listed'} · {e.tiers} ticket {e.tiers === 1 ? 'type' : 'types'}</span>
            </a>
        ),
    },
    { id: 'organization', header: 'Organization', cell: ({ row }) => <OrgLink id={row.original.organization.id} name={row.original.organization.name} /> },
    { id: 'date', header: 'When', meta: { sort: 'date' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.date ?? 'No date'}</span> },
    { id: 'status', header: 'Status', cell: ({ row }) => <Pill value={row.original.status} /> },
    { id: 'tickets', header: 'Tickets', meta: { sort: 'tickets', className: 'text-right' }, cell: ({ row }) => row.original.tickets },
    {
        id: 'actions',
        header: '',
        meta: { className: 'text-right' },
        cell: ({ row: { original: e } }) => (
            <div className="flex justify-end gap-1.5">
                <button type="button" title={e.sponsored ? 'Stop sponsoring its fees' : "Sponsor its fees: VENTIQ won't charge them"} onClick={() => router.post(route('ventiq.events.sponsor', e.id), {}, { preserveScroll: true })} className={e.sponsored ? 'inline-flex items-center gap-1 rounded-full bg-lilac px-3 py-1 text-[10px] font-black uppercase tracking-widest text-lilac-ink' : smallButton}>
                    <Gift className="size-3" />{e.sponsored ? 'Sponsored' : 'Sponsor'}
                </button>
                {e.url && <a href={e.url} target="_blank" rel="noopener" className={smallButton} title="Public page"><ExternalLink className="size-3" /></a>}
                <a href={organizerView(e.organization.id, { id: e.id, to: 'edit' })} className={smallButton}>Edit</a>
            </div>
        ),
    },
];

/** Every event on VENTIQ. */
export default function Events({ rows, state, statuses }: { rows: Paginated<Row>; state: TableState; statuses: { value: string; label: string }[] }) {
    return (
        <AdminLayout title="Events" subtitle="Every event on VENTIQ. Opening one takes you into its organizer view, on its behalf.">
            <Head title="Events" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Event or organization"
                filters={[
                    { key: 'status', label: 'Status', options: statuses },
                    { key: 'when', label: 'When', options: [{ value: 'upcoming', label: 'Upcoming' }, { value: 'past', label: 'Past' }] },
                    { key: 'mode', label: 'Tickets', options: [{ value: 'paid', label: 'Paid' }, { value: 'free', label: 'Free' }] },
                    { key: 'sponsored', label: 'Fees', options: [{ value: 'yes', label: 'Sponsored' }, { value: 'no', label: 'Charged' }] },
                ]}
                empty="No events match."
            />
        </AdminLayout>
    );
}
