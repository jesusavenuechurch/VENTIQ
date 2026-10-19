import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

// VENTIQ buttons: rounded, black-weight uppercase labels.
const buttonVariants = cva(
    "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-2xl font-black uppercase tracking-[0.2em] transition-all active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50",
    {
        variants: {
            variant: {
                // Orange: the one thing to do on the page.
                default: 'bg-action text-white hover:bg-brand',
                brand: 'bg-brand text-white hover:bg-slate-900',
                dark: 'bg-slate-900 text-white hover:bg-brand',
                outline: 'border border-border bg-white text-gray-500 hover:text-brand',
                ghost: 'text-gray-400 hover:text-brand',
                destructive: 'bg-white border border-border text-gray-500 hover:text-destructive',
                link: 'text-brand underline-offset-4 hover:underline normal-case tracking-normal font-bold',
            },
            size: {
                default: 'px-6 py-5 text-xs',
                sm: 'rounded-full px-3 py-1.5 text-[10px] tracking-widest',
                lg: 'px-8 py-6 text-xs tracking-[0.3em]',
                icon: 'size-10 rounded-full',
            },
        },
        defaultVariants: { variant: 'default', size: 'default' },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> & VariantProps<typeof buttonVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : 'button';
    return <Comp data-slot="button" className={cn(buttonVariants({ variant, size, className }))} {...props} />;
}

export { Button, buttonVariants };
