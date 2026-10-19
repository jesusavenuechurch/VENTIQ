import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, organizerView } from '@/components/admin/cells';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { cn, money } from '@/lib/utils';

interface Row {
    id: number;
    name: string;
    event: { id: number; name: string | null; organizationId: number | null; organization: string | null };
    price: number;
    available: number | null;
    sold: number;
    admits: number;
    color: string | null;
    active: boolean;
}

const columns: Column<Row>[] = [
    {
        id: 'name',
        header: 'Ticket type',
        meta: { sort: 'name' },
        cell: ({ row: { original: t } }) => (
            <div className="flex items-center gap-2">
                <span className="size-3 shrink-0 rounded-full border border-white shadow" style={{ background: t.color ?? '#1D4069' }} />
                <span className="font-black text-brand">{t.name}</span>
                {t.admits > 1 && <span className="text-[11px] text-gray-400">admits {t.admits}</span>}
            </div>
        ),
    },
    {
        id: 'event',
        header: 'Event',
        cell: ({ row: { original: t } }) => (
            <div className="min-w-0">
                {t.event.organizationId ? <a href={organizerView(t.event.organizationId, { id: t.event.id, to: 'edit' })} className="font-bold text-gray-700 hover:text-brand">{t.event.name}</a> : t.event.name}
                <p className="text-[11px]"><OrgLink id={t.event.organizationId} name={t.event.organization} /></p>
            </div>
        ),
    },
    { id: 'price', header: 'Price', meta: { sort: 'price', className: 'text-right' }, cell: ({ row }) => <span className="font-bold">{money(row.original.price)}</span> },
    {
        id: 'sold',
        header: 'Sold',
        meta: { sort: 'sold', className: 'text-right' },
        cell: ({ row: { original: t } }) => {
            const low = t.available !== null && t.sold >= t.available * 0.8;
            return <span className={cn('font-bold', low ? 'text-action-ink' : 'text-gray-600')}>{t.sold}{t.available !== null && ` / ${t.available}`}</span>;
        },
    },
    {
        id: 'active',
        header: 'On sale',
        cell: ({ row }) => (row.original.active ? <span className="font-bold text-mint-ink">Yes</span> : <span className="text-gray-300">No</span>),
    },
];

const bulkButton = 'rounded-full bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest hover:bg-white/20';

/** Every ticket type, with price and on-sale changes to many at once. */
export default function Tiers({ rows, state }: { rows: Paginated<Row>; state: TableState }) {
    const [mode, setMode] = useState<'set_price' | 'change_price'>('set_price');
    const [value, setValue] = useState('');

    const send = (ids: number[], action: string, clear: () => void, v?: string) =>
        router.post(route('ventiq.tiers.bulk'), { ids, action, value: v ?? null }, { preserveScroll: true, onSuccess: clear });

    return (
        <AdminLayout title="Ticket types" subtitle="Every ticket type on every event. Pick some to change their price or take them off sale. A new price is for tickets bought from now on.">
            <Head title="Ticket types" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                rowId={(t) => t.id}
                searchPlaceholder="Ticket type or event"
                filters={[
                    { key: 'active', label: 'On sale', options: [{ value: 'yes', label: 'On sale' }, { value: 'no', label: 'Off sale' }] },
                    { key: 'low', label: 'Selling out', options: [{ value: '1', label: '80% or more sold' }] },
                ]}
                bulk={(ids, clear) => (
                    <>
                        <select aria-label="Price change" value={mode} onChange={(e) => setMode(e.target.value as typeof mode)} className="rounded-full bg-white/10 px-3 py-1.5 text-[11px] font-bold text-white [&>option]:text-brand">
                            <option value="set_price">Set price to (M)</option>
                            <option value="change_price">Change price by (%)</option>
                        </select>
                        <input aria-label="Value" type="number" step="0.01" value={value} onChange={(e) => setValue(e.target.value)} placeholder={mode === 'set_price' ? '150' : '-10'} className="w-24 rounded-full bg-white px-3 py-1.5 text-[12px] font-bold text-brand" />
                        <button type="button" disabled={value === ''} onClick={() => send(ids, mode, clear, value)} className={cn(bulkButton, 'disabled:opacity-40')}>Apply</button>
                        <span className="mx-1 h-5 w-px bg-white/20" />
                        <button type="button" onClick={() => send(ids, 'activate', clear)} className={bulkButton}>Put on sale</button>
                        <button type="button" onClick={() => send(ids, 'deactivate', clear)} className={bulkButton}>Take off sale</button>
                    </>
                )}
                empty="No ticket types match."
            />
        </AdminLayout>
    );
}
