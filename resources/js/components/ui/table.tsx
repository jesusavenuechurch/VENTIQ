import * as React from 'react';
import { cn } from '@/lib/utils';

function Table({ className, ...props }: React.ComponentProps<'table'>) {
    return (
        <div className="relative w-full overflow-x-auto">
            <table className={cn('w-full caption-bottom text-[13px]', className)} {...props} />
        </div>
    );
}

function TableHeader({ className, ...props }: React.ComponentProps<'thead'>) {
    return <thead className={cn('bg-slate-50 [&_tr]:border-b [&_tr]:border-gray-100', className)} {...props} />;
}

function TableBody({ className, ...props }: React.ComponentProps<'tbody'>) {
    return <tbody className={cn('[&_tr:last-child]:border-0', className)} {...props} />;
}

function TableRow({ className, ...props }: React.ComponentProps<'tr'>) {
    return <tr className={cn('border-b border-gray-50 transition-colors hover:bg-slate-50/60', className)} {...props} />;
}

function TableHead({ className, ...props }: React.ComponentProps<'th'>) {
    return <th className={cn('h-10 whitespace-nowrap px-4 text-left align-middle text-[10px] font-black uppercase tracking-widest text-gray-400', className)} {...props} />;
}

function TableCell({ className, ...props }: React.ComponentProps<'td'>) {
    return <td className={cn('px-4 py-3 align-middle', className)} {...props} />;
}

export { Table, TableHeader, TableBody, TableRow, TableHead, TableCell };
