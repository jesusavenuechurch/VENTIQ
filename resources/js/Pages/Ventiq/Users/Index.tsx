import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';

export interface UserRow {
    id: number;
    name: string;
    email: string;
    avatar: string;
    role: string | null;
    roleLabel: string | null;
    organization: { id: number; name: string } | null;
    verified: boolean;
    google: boolean;
    lastActive: string | null;
    created: string | null;
    isYou: boolean;
}

const columns: Column<UserRow>[] = [
    {
        id: 'name',
        header: 'Person',
        meta: { sort: 'name' },
        cell: ({ row: { original: u } }) => (
            <Link href={route('ventiq.users.edit', u.id)} className="flex min-w-0 items-center gap-3">
                <img src={u.avatar} alt="" className="size-8 shrink-0 rounded-full bg-slate-100" />
                <span className="min-w-0">
                    <span className="block font-black text-brand hover:text-action">{u.name} {u.isYou && <span className="text-[11px] font-bold text-gray-400">(you)</span>}</span>
                    <span className="block truncate text-[11px] text-gray-400">{u.email}{!u.verified && ' · not verified'}{u.google && ' · Google'}</span>
                </span>
            </Link>
        ),
    },
    {
        id: 'organization',
        header: 'Organization',
        cell: ({ row: { original: u } }) => (u.organization ? <Link href={route('ventiq.organizations.edit', u.organization.id)} className="font-bold text-gray-600 hover:text-brand">{u.organization.name}</Link> : <span className="text-gray-300">None</span>),
    },
    { id: 'role', header: 'Role', cell: ({ row }) => <span className="text-[11px] font-black uppercase tracking-widest text-gray-500">{row.original.roleLabel ?? '—'}</span> },
    { id: 'active', header: 'Last active', meta: { sort: 'active' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.lastActive ?? '—'}</span> },
    { id: 'created', header: 'Joined', meta: { sort: 'created' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.created}</span> },
];

/** Every account on VENTIQ. */
export default function Users({ rows, state, roles }: { rows: Paginated<UserRow>; state: TableState; roles: { value: string; label: string }[] }) {
    return (
        <AdminLayout
            title="Users"
            subtitle="Every account. Organizations manage their own teams; this is for fixing things, and for VENTIQ's staff and agents."
            actions={<Link href={route('ventiq.users.create')} className="rounded-2xl bg-brand px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action"><Plus className="mr-1 inline size-3.5" />New user</Link>}
        >
            <Head title="Users" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Name or email"
                filters={[
                    { key: 'role', label: 'Role', options: [...roles, { value: 'none', label: 'No role' }] },
                    { key: 'organization', label: 'Organization', options: [{ value: 'none', label: 'None (VENTIQ, agents)' }] },
                ]}
                empty="Nobody matches."
            />
        </AdminLayout>
    );
}
