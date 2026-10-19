import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import AuthLayout, { authButton, authInput } from '@/Layouts/AuthLayout';
import { Field } from '@/components/field';

interface Invite {
    token: string;
    email: string;
    organization: string;
    role: string;
    pending: boolean;
    accepted: boolean;
}

/** Accept a team invite: a name and password, and you're in, with the role you were invited for. */
export default function InviteAccept({ invite }: { invite: Invite }) {
    const form = useForm({ name: '', password: '', password_confirmation: '' });
    const error = Object.values(form.errors)[0];

    if (!invite.pending) {
        return (
            <AuthLayout label="Team invite">
                <Head title="Invite" />
                <div className="text-center">
                    <p className="text-[15px] font-black text-brand">{invite.accepted ? 'This invite has already been used' : 'This invite has expired'}</p>
                    <p className="mt-2 text-[12px] text-gray-500">
                        {invite.accepted ? `If it was you, sign in with ${invite.email}.` : `Invites work for 7 days. Ask ${invite.organization}'s admin to send you a new one.`}
                    </p>
                    <Link href={route('login', { email: invite.email })} className={`${authButton} mt-6`}>Go to sign in</Link>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout label="You've been invited">
            <Head title={`Join ${invite.organization}`} />
            <h1 className="mb-1 text-center text-xl font-black text-brand">Join {invite.organization}</h1>
            <p className="mb-6 text-center text-[12px] text-gray-500">as {invite.role}, with {invite.email}</p>
            {error && <p role="alert" className="mb-4 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700"><CircleAlert className="mr-1 inline size-4" />{error}</p>}
            {/* Accepting signs you in: the page after loads in full. */}
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('organization.invite.submit', invite.token)); }} className="space-y-4">
                <Field label="Your name" htmlFor="name">
                    <input id="name" required autoComplete="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={authInput} />
                </Field>
                <Field label="Choose a password" htmlFor="password" hint="At least 8 characters.">
                    <input id="password" type="password" required autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className={authInput} />
                </Field>
                <Field label="The same again" htmlFor="password_confirmation">
                    <input id="password_confirmation" type="password" required autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} className={authInput} />
                </Field>
                <button disabled={form.processing} className={authButton}>Join {invite.organization}</button>
            </form>
        </AuthLayout>
    );
}
