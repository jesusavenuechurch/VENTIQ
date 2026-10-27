import { Head, router } from '@inertiajs/react';
import { ExternalLink, Gift, Plus } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { OrgLink, Pill, organizerView, smallButton } from '@/components/admin/cells';
import { DataTable, type Column, type TableState } from '@/components/data-table';
import type { Paginated } from '@/components/pagination';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';

interface Row {
    id: number;
    name: string;
    organization: { id: number; name: string | null };
    date: string | null;
    status: string;
    kind: 'event' | 'session' | 'programme';
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
        cell: ({ row: { original: e } }) =>
            e.kind === 'event' ? (
                <a href={organizerView(e.organization.id, { id: e.id })} className="block min-w-0">
                    <span className="block font-black text-brand hover:text-action">{e.name}</span>
                    <span className="block text-[11px] text-gray-400">{e.mode === 'free' ? 'Free' : 'Paid'}{!e.public && ' · not listed'} · {e.tiers} ticket {e.tiers === 1 ? 'type' : 'types'}</span>
                </a>
            ) : (
                <span className="block min-w-0">
                    <span className="block font-black text-brand">{e.name}</span>
                    <span className="block text-[11px] text-gray-400">{e.kind === 'session' ? "A session's attendee list" : 'A programme'}, in Sessions</span>
                </span>
            ),
    },
    { id: 'organization', header: 'Organization', cell: ({ row }) => <OrgLink id={row.original.organization.id} name={row.original.organization.name} /> },
    { id: 'date', header: 'When', meta: { sort: 'date' }, cell: ({ row }) => <span className="whitespace-nowrap text-gray-500">{row.original.date ?? 'No date'}</span> },
    { id: 'status', header: 'Status', cell: ({ row }) => (row.original.kind === 'event' ? <Pill value={row.original.status} /> : <Pill value={row.original.kind} />) },
    { id: 'tickets', header: 'Tickets', meta: { sort: 'tickets', className: 'text-right' }, cell: ({ row }) => row.original.tickets },
    {
        id: 'actions',
        header: '',
        meta: { className: 'text-right' },
        cell: ({ row: { original: e } }) => e.kind !== 'event' ? null : (
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
export default function Events({ rows, state, statuses, organizations }: { rows: Paginated<Row>; state: TableState; statuses: { value: string; label: string }[]; organizations: { value: number; label: string }[] }) {
    const [creating, setCreating] = useState(false);
    const [org, setOrg] = useState('');

    return (
        <AdminLayout
            title="Events"
            subtitle="Every ticketed event on VENTIQ. Opening one takes you into its organizer view, on its behalf. Sessions and programmes are under Kind."
            actions={<Button type="button" variant="brand" className="px-5 py-3 text-[10px]" onClick={() => setCreating(true)}><Plus className="size-3.5" />Create an event</Button>}
        >
            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent className="max-w-md rounded-[2rem]">
                    <DialogTitle className="text-lg font-black text-brand">Create an event</DialogTitle>
                    <p className="text-[12px] text-gray-500">For which organization? Its event form opens, in its organizer view, on its behalf.</p>
                    <Field label="Organization" htmlFor="create-org">
                        <NativeSelect id="create-org" value={org} onChange={(e) => setOrg(e.target.value)}>
                            <option value="" disabled>Choose…</option>
                            {organizations.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </NativeSelect>
                    </Field>
                    <Button type="button" variant="brand" disabled={!org} onClick={() => (window.location.href = route('organizer.act-as.start', { organization: org, to: 'create' }))}>
                        Open the event form
                    </Button>
                </DialogContent>
            </Dialog>
            <Head title="Events" />
            <DataTable
                rows={rows}
                columns={columns}
                state={state}
                searchPlaceholder="Event or organization"
                filters={[
                    { key: 'kind', label: 'Kind', options: [{ value: 'session', label: "Sessions' attendee lists" }, { value: 'programme', label: 'Programmes' }, { value: 'all', label: 'Every kind' }] },
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
