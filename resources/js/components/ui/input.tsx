import * as React from 'react';
import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'w-full min-w-0 rounded-2xl border-2 border-slate-50 bg-slate-50 px-6 py-4 font-bold text-gray-900 outline-none transition-all',
                'focus:border-action focus:bg-white aria-invalid:border-destructive/60',
                'file:border-0 file:bg-transparent disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
