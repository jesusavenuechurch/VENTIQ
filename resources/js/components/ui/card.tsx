import * as React from 'react';
import { cn } from '@/lib/utils';

function Card({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card" className={cn('overflow-hidden rounded-[2.5rem] border border-gray-100 bg-card text-card-foreground shadow-lg shadow-gray-200/40', className)} {...props} />;
}

function CardHeader({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-header" className={cn('p-8 pb-0 sm:p-10 sm:pb-0', className)} {...props} />;
}

function CardTitle({ className, ...props }: React.ComponentProps<'h2'>) {
    return <h2 data-slot="card-title" className={cn('text-2xl font-black uppercase tracking-tight text-gray-900', className)} {...props} />;
}

function CardDescription({ className, ...props }: React.ComponentProps<'p'>) {
    return <p data-slot="card-description" className={cn('mt-2 text-sm font-bold text-gray-500', className)} {...props} />;
}

function CardContent({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-content" className={cn('p-8 sm:p-10', className)} {...props} />;
}

function CardFooter({ className, ...props }: React.ComponentProps<'div'>) {
    return <div data-slot="card-footer" className={cn('border-t border-gray-50 p-8 pt-6 sm:px-10', className)} {...props} />;
}

export { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter };
