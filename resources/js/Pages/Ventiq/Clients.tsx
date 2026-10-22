import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink } from '@/components/admin/cells';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { avatarUrl } from '@/lib/avatar';

interface Row {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    organization: { id: number; name: string | null };
    tickets: number;
    created: string | null;
}

const columns: Column<Row>[] = [
    {
        id: 'name',
        header: 'Person',
        meta: { sort: 'name' },
        cell: ({ row: { original: c } }) => (
            <div className="flex min-w-0 items-center gap-3">
                <img src={avatarUrl(c.phone)} alt="" className="size-8 shrink-0 rounded-full bg-slate-100" />
                <div className="min-w-0">
                    <p className="font-black text-brand">{c.name}</p>
                    <p className="truncate text-[11px] text-gray-400">{c.phone}{c.email && ` · ${c.email}`}</p>
                </div>
            </div>
        ),
    },
    { id: 'organization', header: 'Organization', cell: ({ row }) => <OrgLink id={row.original.organization.id} name={row.original.organization.name} /> },
    {
        id: 'tickets',
        header: 'Tickets',
        meta: { sort: 'tickets', className: 'text-right' },
        cell: ({ row: { original: c } }) => <a href={route('ventiq.tickets.index', { q: c.phone ?? c.name })} className="font-bold text-brand hover:underline">{c.tickets}</a>,
    },
    { id: 'created', header: 'First seen', meta: { sort: 'created' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.created}</span> },
];

/** Every attendee contact, across organizations: each organization keeps its own. */
export default function Clients({ rows, state }: { rows: Paginated<Row>; state: TableState }) {
    return (
        <AdminLayout title="Clients" subtitle="Everyone who has registered, per organization. The same person shows once for each organization they've booked with.">
            <Head title="Clients" />
            <DataTable rows={rows} columns={columns} state={state} searchPlaceholder="Name, phone or email" empty="Nobody matches." />
        </AdminLayout>
    );
}
