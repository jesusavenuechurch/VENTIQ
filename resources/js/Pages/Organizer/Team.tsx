import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Send, UserMinus } from 'lucide-react';
import { useState } from 'react';
import OrganizerLayout from '@/Layouts/OrganizerLayout';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { avatarUrl } from '@/lib/avatar';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Role {
    value: string;
    name: string;
    description: string;
}

interface Member {
    id: number;
    name: string;
    email: string;
    role: string | null;
    isYou: boolean;
}

interface Invite {
    id: number;
    email: string;
    roleLabel: string;
    expires: string;
}

const roleBadge: Record<string, string> = {
    org_admin: 'bg-brand text-white',
    staff: 'bg-mint text-mint-ink',
    scanner: 'bg-action-soft text-action-ink',
    viewer: 'bg-slate-100 text-slate-500',
};

/** The organization's people and what each may do. Everyone sees it; managers change it. */
export default function Team({
    members,
    invites,
    roles,
    canManage,
    seatsLeft,
}: {
    members: Member[];
    invites: Invite[];
    roles: Role[];
    canManage: boolean;
    seatsLeft: number | null;
}) {
    const { errors } = usePage<SharedProps>().props;
    const invite = useForm({ email: '', role: 'staff' });
    const [removing, setRemoving] = useState<Member | null>(null);
    const [busy, setBusy] = useState(false);
    const label = (role: string | null) => roles.find((r) => r.value === role)?.name ?? 'No role';

    function sendInvite(e: React.FormEvent) {
        e.preventDefault();
        invite.post(route('organizer.team.invite'), { preserveScroll: true, onSuccess: () => invite.reset('email') });
    }

    function changeRole(member: Member, role: string) {
        router.put(route('organizer.team.role', member.id), { role }, { preserveScroll: true });
    }

    function remove() {
        if (!removing) return;
        setBusy(true);
        router.delete(route('organizer.team.remove', removing.id), {
            preserveScroll: true,
            onFinish: () => {
                setBusy(false);
                setRemoving(null);
            },
        });
    }

    // Errors from role changes and removals (the invite form shows its own).
    const otherErrors = Object.entries(errors ?? {}).filter(([key]) => key !== 'email' && key !== 'role');

    return (
        <OrganizerLayout title="Settings" settings subtitle="The people who make your events happen, and what each of them can do.">
            <Head title="Team" />
            <div className="max-w-4xl">
                {otherErrors.length > 0 && (
                    <div className="mb-6 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">
                        {otherErrors.map(([key, message]) => <p key={key}>{message}</p>)}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-5">
                    <div className="space-y-6 lg:col-span-3">
                        <div className="divide-y divide-gray-50 rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                            {members.map((member) => (
                                <div key={member.id} className="flex flex-wrap items-center justify-between gap-3 p-5">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <img src={avatarUrl(member.email)} alt="" className="size-11 shrink-0 rounded-full bg-slate-100" />
                                        <div className="min-w-0">
                                            <p className="text-[14px] font-black text-brand">
                                                {member.name} {member.isYou && <span className="text-[11px] font-bold text-gray-400">(you)</span>}
                                            </p>
                                            <p className="truncate text-[11px] font-medium text-gray-500">{member.email}</p>
                                        </div>
                                    </div>

                                    {canManage && !member.isYou ? (
                                        <div className="flex items-center gap-2">
                                            <select
                                                aria-label={`Role for ${member.name}`}
                                                value={member.role ?? ''}
                                                onChange={(e) => changeRole(member, e.target.value)}
                                                className="cursor-pointer rounded-full border-0 bg-slate-50 py-1.5 pl-3 pr-8 text-[11px] font-black text-brand focus:outline-action"
                                            >
                                                {!member.role && <option value="" disabled>No role</option>}
                                                {roles.map((r) => <option key={r.value} value={r.value}>{r.name}</option>)}
                                            </select>
                                            <button
                                                type="button"
                                                onClick={() => setRemoving(member)}
                                                aria-label={`Remove ${member.name}`}
                                                className="grid size-8 place-items-center rounded-full border border-gray-200 bg-white text-gray-400 hover:text-rose-600"
                                            >
                                                <UserMinus className="size-3.5" />
                                            </button>
                                        </div>
                                    ) : (
                                        <span className={cn('rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest', roleBadge[member.role ?? ''] ?? 'bg-slate-100 text-slate-500')}>
                                            {label(member.role)}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>

                        {invites.length > 0 && (
                            <div>
                                <p className="mb-2 ml-1 text-[10px] font-black uppercase tracking-widest text-gray-400">Invited, not joined yet</p>
                                <div className="divide-y divide-gray-50 rounded-[1.5rem] border border-gray-100 bg-white shadow-sm">
                                    {invites.map((inv) => (
                                        <div key={inv.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                            <div className="min-w-0">
                                                <p className="truncate text-[13px] font-bold text-brand">{inv.email}</p>
                                                <p className="text-[11px] font-medium text-gray-400">{inv.roleLabel} · link works until {inv.expires}</p>
                                            </div>
                                            {canManage && (
                                                <button
                                                    type="button"
                                                    onClick={() => router.delete(route('organizer.team.invite.revoke', inv.id), { preserveScroll: true })}
                                                    className="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-rose-600"
                                                >
                                                    Cancel invite
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="space-y-6 lg:col-span-2">
                        {canManage ? (
                            <form onSubmit={sendInvite} className="space-y-4 rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm">
                                <p className="text-[15px] font-black text-brand">Invite someone</p>
                                <Field label="Their email" htmlFor="email" error={invite.errors.email}>
                                    <Input id="email" type="email" required value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} className="rounded-2xl px-4 py-3 text-[14px] font-semibold" />
                                </Field>
                                <div>
                                    <span className="mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">What they can do</span>
                                    <div className="space-y-2">
                                        {roles.map((r) => (
                                            <label key={r.value} className={cn('flex cursor-pointer gap-3 rounded-2xl border-2 p-3 transition-all', invite.data.role === r.value ? 'border-action bg-action-soft' : 'border-slate-50 bg-slate-50')}>
                                                <input type="radio" name="role" value={r.value} checked={invite.data.role === r.value} onChange={() => invite.setData('role', r.value)} className="mt-0.5 accent-action" />
                                                <span>
                                                    <span className="block text-[12px] font-black text-brand">{r.name}</span>
                                                    <span className="block text-[11px] font-medium text-gray-500">{r.description}</span>
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    {invite.errors.role && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-500">{invite.errors.role}</p>}
                                </div>
                                <Button type="submit" variant="brand" className="w-full py-3 text-[11px]" disabled={invite.processing}>
                                    <Send className="size-3.5" />Send invite
                                </Button>
                                {seatsLeft !== null && (
                                    <p className="text-center text-[11px] font-medium text-gray-400">
                                        {seatsLeft} team {seatsLeft === 1 ? 'place' : 'places'} left on your package.
                                    </p>
                                )}
                            </form>
                        ) : (
                            <div className="rounded-[1.5rem] border border-gray-100 bg-white p-6 shadow-sm">
                                <p className="text-[12px] font-medium text-gray-500">Ask an Admin on your team to invite people or change what they can do.</p>
                            </div>
                        )}

                        <div className="space-y-2 rounded-[1.5rem] bg-slate-50 p-5">
                            <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Roles</p>
                            {roles.map((r) => (
                                <p key={r.value} className="text-[11px] text-gray-500"><strong className="text-brand">{r.name}:</strong> {r.description}</p>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title={`Remove ${removing?.name} from the team?`}
                confirmLabel="Remove"
                processing={busy}
                onConfirm={remove}
            >
                They lose access straight away. Their VENTIQ account stays.
            </ConfirmDialog>
        </OrganizerLayout>
    );
}
