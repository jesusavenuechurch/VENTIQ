import * as React from 'react';
import { cn } from '@/lib/utils';

/** A plain select in VENTIQ's field style: the phone's own picker on mobile. */
function NativeSelect({ className, ...props }: React.ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'w-full cursor-pointer appearance-none rounded-2xl border-2 border-slate-50 bg-slate-50 px-4 py-3 text-[14px] font-semibold text-gray-900 outline-none transition-all focus:border-action focus:bg-white',
                className,
            )}
            {...props}
        />
    );
}

export { NativeSelect };
