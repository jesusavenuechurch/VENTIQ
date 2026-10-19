import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const alertVariants = cva('w-full rounded-3xl p-5 text-[13px] font-bold [&>svg]:mr-2 [&>svg]:inline [&>svg]:size-4', {
    variants: {
        variant: {
            // Good news: paid, saved, sent.
            success: 'bg-mint text-mint-ink',
            // Something waiting on the person.
            action: 'bg-action-soft text-action-ink',
            // VENTIQ itself: fees, sponsored, online via VENTIQ.
            ventiq: 'bg-lilac text-lilac-ink',
            destructive: 'border-2 border-rose-100 bg-rose-50 text-rose-600',
        },
    },
    defaultVariants: { variant: 'success' },
});

function Alert({ className, variant, ...props }: React.ComponentProps<'div'> & VariantProps<typeof alertVariants>) {
    return <div data-slot="alert" role="alert" className={cn(alertVariants({ variant }), className)} {...props} />;
}

export { Alert };
