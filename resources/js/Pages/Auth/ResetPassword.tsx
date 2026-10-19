import { Head, useForm } from '@inertiajs/react';
import AuthLayout, { authButton, authInput } from '@/Layouts/AuthLayout';
import { Field } from '@/components/field';

/** Choose a password from an emailed link (new agents get one when they're approved). */
export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    return (
        <AuthLayout label="Choose a password">
            <Head title="Choose a password" />
            <p className="mb-6 text-center text-[13px] text-gray-500">For <strong className="text-brand">{email}</strong>. At least 8 characters.</p>
            {form.errors.email && <p role="alert" className="mb-4 rounded-2xl border border-rose-100 bg-rose-50 p-4 text-[12px] font-bold text-rose-700">{form.errors.email}</p>}
            {/* A full page load after: it signs you in. */}
            <form onSubmit={(e) => { e.preventDefault(); form.post(route('password.update')); }} className="space-y-4">
                <Field label="New password" htmlFor="password" error={form.errors.password}>
                    <input id="password" type="password" required autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} className={authInput} />
                </Field>
                <Field label="The same again" htmlFor="password_confirmation">
                    <input id="password_confirmation" type="password" required autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} className={authInput} />
                </Field>
                <button disabled={form.processing} className={authButton}>Save and sign in</button>
            </form>
        </AuthLayout>
    );
}
