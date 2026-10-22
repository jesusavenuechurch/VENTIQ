import * as React from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** A labelled form field with its hint and its validation error. */
export function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label: React.ReactNode;
    htmlFor: string;
    error?: string;
    hint?: React.ReactNode;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={cn('space-y-1', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && <p className="ml-1 text-[11px] font-medium text-gray-400">{hint}</p>}
            {error && <p className="ml-1 text-[11px] font-bold text-rose-500">{error}</p>}
        </div>
    );
}
