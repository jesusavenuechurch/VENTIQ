import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva('inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-widest', {
    variants: {
        variant: {
            default: 'bg-slate-100 text-gray-500',
            success: 'bg-mint text-mint-ink',
            action: 'bg-action-soft text-action-ink',
            ventiq: 'bg-lilac text-lilac-ink',
            brand: 'bg-brand text-white',
        },
    },
    defaultVariants: { variant: 'default' },
});

function Badge({ className, variant, ...props }: React.ComponentProps<'span'> & VariantProps<typeof badgeVariants>) {
    return <span data-slot="badge" className={cn(badgeVariants({ variant }), className)} {...props} />;
}

export { Badge, badgeVariants };
