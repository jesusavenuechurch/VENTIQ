import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { cn } from '@/lib/utils';

interface Row {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    slug: string;
    active: boolean;
    events: number;
    members: number;
    payout: boolean;
    created: string | null;
}

const columns: Column<Row>[] = [
    {
        id: 'name',
        header: 'Organization',
        meta: { sort: 'name' },
        cell: ({ row: { original: o } }) => (
            <Link href={route('ventiq.organizations.edit', o.id)} className="block min-w-0">
                <span className="block font-black text-brand hover:text-action">{o.name}</span>
                <span className="block text-[11px] text-gray-400">{o.email}{o.phone && ` · ${o.phone}`}</span>
            </Link>
        ),
    },
    { id: 'events', header: 'Events', meta: { sort: 'events', className: 'text-right' }, cell: ({ row }) => row.original.events },
    { id: 'members', header: 'Team', meta: { className: 'text-right' }, cell: ({ row }) => row.original.members },
    {
        id: 'payout',
        header: 'Payout account',
        cell: ({ row }) => (row.original.payout ? <span className="text-mint-ink">On file</span> : <span className="text-gray-300">None</span>),
    },
    {
        id: 'active',
        header: 'Status',
        cell: ({ row }) => (
            <span className={cn('rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-widest', row.original.active ? 'bg-mint text-mint-ink' : 'bg-slate-100 text-slate-500')}>
                {row.original.active ? 'Active' : 'Off'}
            </span>
        ),
    },
    { id: 'created', header: 'Joined', meta: { sort: 'created' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.created}</span> },
    {
        id: 'open',
        header: '',
        meta: { className: 'text-right' },
        cell: ({ row }) => (
            <a href={route('organizer.act-as.start', row.original.id)} className="whitespace-nowrap rounded-full border border-gray-200 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-brand">
                Organizer view
            </a>
        ),
    },
];

/** Every organization on VENTIQ. */
export default function Organizations({ rows, state }: { rows: Paginated<Row>; state: TableState }) {
    return (
        <AdminLayout
            title="Organizations"
            subtitle="Everyone hosting on VENTIQ. Open one's organizer view to help with its events, details or team."
            actions={
                <Link href={route('ventiq.organizations.create')} className="rounded-2xl bg-brand px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action">
                    <Plus className="mr-1 inline size-3.5" />New organization
                </Link>
            }
        >
            <Head title="Organizations" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Name, email, phone or web address"
                filters={[
                    { key: 'active', label: 'Status', options: [{ value: 'yes', label: 'Active' }, { value: 'no', label: 'Off' }] },
                    { key: 'payout', label: 'Payout account', options: [{ value: 'yes', label: 'On file' }, { value: 'no', label: 'None' }] },
                ]}
                empty="No organizations match."
            />
        </AdminLayout>
    );
}
