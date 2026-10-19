import * as React from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogTitle } from '@/components/ui/dialog';

/**
 * "Are you sure?" before something that can't be undone with one click.
 * Open while `open` is set; `onConfirm` does the work.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    children,
    confirmLabel,
    destructive = true,
    processing = false,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: React.ReactNode;
    children?: React.ReactNode;
    confirmLabel: string;
    destructive?: boolean;
    processing?: boolean;
    onConfirm: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="rounded-[2rem]">
                <div className="space-y-4 p-7">
                    <DialogTitle className="pr-8 text-lg normal-case not-italic leading-tight tracking-normal">{title}</DialogTitle>
                    {children && <div className="text-[13px] font-medium text-gray-500">{children}</div>}
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">Keep it</Button>
                        </DialogClose>
                        <Button type="button" variant="brand" className={destructive ? 'bg-rose-600 hover:bg-rose-700' : undefined} disabled={processing} onClick={onConfirm}>
                            {confirmLabel}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
