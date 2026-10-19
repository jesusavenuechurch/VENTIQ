import { router } from '@inertiajs/react';
import { type ColumnDef, flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Pagination, type Paginated } from '@/components/pagination';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

/** App\Support\Admin\AdminTable::state(): what the list is showing. */
export interface TableState {
    q: string;
    sort: string | null;
    dir: 'asc' | 'desc';
    filters: Record<string, string>;
}

export interface TableFilter {
    key: string;
    label: string;
    options: { value: string; label: string }[];
}

/** A column the server can sort by names it in meta.sort. */
export type Column<T> = ColumnDef<T> & { meta?: { sort?: string; className?: string } };

/**
 * A list the server pages, sorts, searches and filters (AdminTable on
 * the PHP side); every change is a visit, so the URL is always shareable.
 */
export function DataTable<T>({
    rows,
    columns,
    state,
    filters = [],
    searchPlaceholder = 'Search…',
    empty = 'Nothing here yet.',
    toolbar,
    only,
    rowId,
    bulk,
}: {
    rows: Paginated<T>;
    columns: Column<T>[];
    state: TableState;
    filters?: TableFilter[];
    searchPlaceholder?: string;
    empty?: React.ReactNode;
    toolbar?: React.ReactNode;
    /** Props to reload on a change (default: all). */
    only?: string[];
    /** With bulk: each row's id, for picking rows. */
    rowId?: (row: T) => number;
    /** Actions on the picked rows; call clear() once done. */
    bulk?: (ids: number[], clear: () => void) => React.ReactNode;
}) {
    const [q, setQ] = useState(state.q);
    const [picked, setPicked] = useState<number[]>([]);
    const selectable = !!(bulk && rowId);
    const pageIds = selectable ? rows.data.map(rowId!) : [];
    const allPicked = pageIds.length > 0 && pageIds.every((id) => picked.includes(id));
    // A new page, sort or search starts with nothing picked.
    useEffect(() => setPicked([]), [rows]);
    const first = useRef(true);

    const visit = (change: Partial<{ q: string; sort: string | null; dir: string; filters: Record<string, string> }>) => {
        const next = { q, sort: state.sort, dir: state.dir, filters: state.filters, ...change };
        const query: Record<string, string> = {};
        if (next.q) query.q = next.q;
        if (next.sort) {
            query.sort = next.sort;
            query.dir = next.dir;
        }
        Object.entries(next.filters).forEach(([k, v]) => v && (query[`filter[${k}]`] = v));
        router.get(window.location.pathname, query, { preserveState: true, preserveScroll: true, replace: true, only });
    };

    // Search as you type, a moment after the last key.
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = window.setTimeout(() => visit({ q }), 350);
        return () => window.clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const table = useReactTable({ data: rows.data, columns, getCoreRowModel: getCoreRowModel(), manualPagination: true, manualSorting: true });

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="absolute left-4 top-1/2 size-3.5 -translate-y-1/2 text-gray-300" />
                    <input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={searchPlaceholder}
                        aria-label="Search"
                        className="w-full rounded-full border border-gray-100 bg-white py-2.5 pl-10 pr-4 text-[13px] font-semibold text-brand outline-none focus:border-action"
                    />
                </div>
                {filters.map((f) => (
                    <NativeSelect
                        key={f.key}
                        aria-label={f.label}
                        value={state.filters[f.key] ?? ''}
                        onChange={(e) => visit({ filters: { ...state.filters, [f.key]: e.target.value } })}
                        className="w-auto rounded-full border-gray-100 bg-white py-2.5 pr-9 text-[12px]"
                    >
                        <option value="">{f.label}: all</option>
                        {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </NativeSelect>
                ))}
                {toolbar}
            </div>

            {selectable && picked.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 rounded-2xl bg-brand px-4 py-3 text-white">
                    <span className="mr-2 text-[11px] font-black uppercase tracking-widest">{picked.length} picked</span>
                    {bulk!(picked, () => setPicked([]))}
                    <button type="button" onClick={() => setPicked([])} className="ml-auto text-[10px] font-black uppercase tracking-widest text-white/70 hover:text-white">Clear</button>
                </div>
            )}

            <div className="overflow-hidden rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((group) => (
                            <TableRow key={group.id} className="hover:bg-transparent">
                                {selectable && (
                                    <TableHead className="w-10">
                                        <input type="checkbox" aria-label="Pick every row on this page" checked={allPicked} onChange={(e) => setPicked(e.target.checked ? pageIds : [])} className="size-4 accent-action" />
                                    </TableHead>
                                )}
                                {group.headers.map((header) => {
                                    const meta = (header.column.columnDef as Column<T>).meta;
                                    const sortKey = meta?.sort;
                                    const on = sortKey && state.sort === sortKey;
                                    const label = flexRender(header.column.columnDef.header, header.getContext());
                                    return (
                                        <TableHead key={header.id} className={meta?.className}>
                                            {sortKey ? (
                                                <button
                                                    type="button"
                                                    onClick={() => visit({ sort: sortKey, dir: on && state.dir === 'asc' ? 'desc' : 'asc' })}
                                                    className={cn('inline-flex items-center gap-1 uppercase hover:text-brand', on && 'text-brand')}
                                                >
                                                    {label}
                                                    {on ? state.dir === 'asc' ? <ArrowUp className="size-3" /> : <ArrowDown className="size-3" /> : <ArrowUpDown className="size-3 opacity-40" />}
                                                </button>
                                            ) : label}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.length === 0 ? (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={columns.length + (selectable ? 1 : 0)} className="py-12 text-center text-[13px] font-bold text-gray-400">{empty}</TableCell>
                            </TableRow>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id} data-state={selectable && picked.includes(rowId!(row.original)) ? 'selected' : undefined} className="data-[state=selected]:bg-action-soft/40">
                                    {selectable && (
                                        <TableCell className="w-10">
                                            <input
                                                type="checkbox"
                                                aria-label="Pick this row"
                                                checked={picked.includes(rowId!(row.original))}
                                                onChange={(e) => {
                                                    const id = rowId!(row.original);
                                                    setPicked((p) => (e.target.checked ? [...p, id] : p.filter((x) => x !== id)));
                                                }}
                                                className="size-4 accent-action"
                                            />
                                        </TableCell>
                                    )}
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id} className={(cell.column.columnDef as Column<T>).meta?.className}>
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-[11px] font-bold text-gray-400">{rows.total.toLocaleString('en-US')} {rows.total === 1 ? 'result' : 'results'}</p>
                <Pagination page={rows} />
            </div>
        </div>
    );
}
