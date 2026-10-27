import { Toaster as Sonner, type ToasterProps } from 'sonner';

/** shadcn's toaster (Sonner): short confirmations like "Link copied", at the bottom on phones. */
function Toaster(props: ToasterProps) {
    return (
        <Sonner
            position="bottom-center"
            toastOptions={{ classNames: { toast: 'rounded-2xl font-sans text-[13px] font-bold', success: 'text-brand' } }}
            {...props}
        />
    );
}

export { Toaster };
