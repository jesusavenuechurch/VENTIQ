import { Head, Link } from '@inertiajs/react';
import { Plus, Star } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';

interface Row {
    id: number;
    name: string;
    description: string | null;
    organization: string | null;
    organizationId: number | null;
    tiers: string[];
    default: boolean;
    created: string | null;
}

const columns: Column<Row>[] = [
    {
        id: 'name',
        header: 'Template',
        meta: { sort: 'name' },
        cell: ({ row: { original: t } }) => (
            <Link href={route('ventiq.tier-templates.edit', t.id)} className="block min-w-0">
                <span className="block font-black text-brand hover:text-action">{t.name} {t.default && <Star className="inline size-3 fill-action text-action" aria-label="Default" />}</span>
                {t.description && <span className="block truncate text-[11px] text-gray-400">{t.description}</span>}
            </Link>
        ),
    },
    { id: 'tiers', header: 'Ticket types', cell: ({ row }) => <span className="text-gray-600">{row.original.tiers.join(', ')}</span> },
    { id: 'organization', header: 'For', cell: ({ row }) => <span className="text-gray-600">{row.original.organization ?? 'Any organization'}</span> },
    { id: 'created', header: 'Made', meta: { sort: 'created' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.created}</span> },
];

/** Saved sets of ticket types to add to an event in one go. */
export default function TierTemplates({ rows, state }: { rows: Paginated<Row>; state: TableState }) {
    return (
        <AdminLayout
            title="Tier templates"
            subtitle="Saved sets of ticket types, e.g. Early bird, General and VIP, to add to an event in one go."
            actions={<Link href={route('ventiq.tier-templates.create')} className="rounded-2xl bg-brand px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action"><Plus className="mr-1 inline size-3.5" />New template</Link>}
        >
            <Head title="Tier templates" />
            <DataTable rows={rows} columns={columns} state={state} searchPlaceholder="Name or description" filters={[{ key: 'default', label: 'Default', options: [{ value: 'yes', label: 'Default only' }] }]} empty="No templates yet." />
        </AdminLayout>
    );
}
