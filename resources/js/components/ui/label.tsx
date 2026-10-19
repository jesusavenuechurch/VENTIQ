import * as React from 'react';
import * as LabelPrimitive from '@radix-ui/react-label';
import { cn } from '@/lib/utils';

// The small grey uppercase labels above VENTIQ fields.
function Label({ className, ...props }: React.ComponentProps<typeof LabelPrimitive.Root>) {
    return (
        <LabelPrimitive.Root
            data-slot="label"
            className={cn('mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400', className)}
            {...props}
        />
    );
}

export { Label };
