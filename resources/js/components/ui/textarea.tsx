import * as React from 'react';
import { cn } from '@/lib/utils';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            className={cn(
                'w-full rounded-2xl border-2 border-slate-50 bg-slate-50 px-4 py-3 text-[14px] font-medium text-gray-900 outline-none transition-all focus:border-action focus:bg-white',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
