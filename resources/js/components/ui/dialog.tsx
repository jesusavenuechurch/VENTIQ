import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogClose = DialogPrimitive.Close;

function DialogContent({
    className,
    children,
    showClose = true,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & { showClose?: boolean }) {
    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="fixed inset-0 z-[80] bg-brand/40 backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0" />
            <DialogPrimitive.Content
                className={cn(
                    'fixed left-1/2 top-1/2 z-[90] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 overflow-hidden rounded-[2.5rem] border border-white/20 bg-white shadow-2xl outline-none',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95',
                    className,
                )}
                {...props}
            >
                {/* VENTIQ's navy-orange stripe across the top of every box. */}
                <div className="h-1.5 w-full bg-gradient-to-r from-brand via-action to-brand" />
                {children}
                {showClose && (
                    <DialogPrimitive.Close className="absolute right-5 top-6 rounded-full p-2 text-gray-300 transition-colors hover:bg-gray-50 hover:text-gray-500" aria-label="Close">
                        <X className="size-4" />
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

function DialogTitle({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Title>) {
    return <DialogPrimitive.Title className={cn('text-2xl font-black uppercase italic tracking-tight text-brand', className)} {...props} />;
}

function DialogDescription({ className, ...props }: React.ComponentProps<typeof DialogPrimitive.Description>) {
    return <DialogPrimitive.Description className={cn('mt-1 text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400', className)} {...props} />;
}

export { Dialog, DialogTrigger, DialogClose, DialogContent, DialogTitle, DialogDescription };
