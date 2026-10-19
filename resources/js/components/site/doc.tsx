import { Head } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';

/** A plain page of text: terms, privacy. */
export function Doc({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) {
    return (
        <SiteLayout>
            <Head title={title} />
            <div className="mx-auto max-w-2xl px-4 py-12 text-brand">
                <h1 className="text-3xl font-black tracking-tight">{title}</h1>
                <p className="mt-2 text-[13px] text-gray-500">{subtitle}</p>
                <div className="mt-8 space-y-7 text-[14px] leading-relaxed text-gray-700 [&_a]:text-brand [&_a]:underline [&_a:hover]:text-action [&_h2]:text-[15px] [&_h2]:font-black [&_h2]:text-brand [&_h2+*]:mt-1 [&_li]:mt-1 [&_p+p]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">{children}</div>
            </div>
        </SiteLayout>
    );
}
