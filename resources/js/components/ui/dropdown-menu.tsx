import * as React from 'react';
import * as Menu from '@radix-ui/react-dropdown-menu';
import { cn } from '@/lib/utils';

const DropdownMenu = Menu.Root;
const DropdownMenuTrigger = Menu.Trigger;

function DropdownMenuContent({ className, sideOffset = 12, ...props }: React.ComponentProps<typeof Menu.Content>) {
    return (
        <Menu.Portal>
            <Menu.Content
                sideOffset={sideOffset}
                className={cn(
                    'z-[70] min-w-44 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xl',
                    'data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-top-1 data-[state=closed]:animate-out data-[state=closed]:fade-out-0',
                    className,
                )}
                {...props}
            />
        </Menu.Portal>
    );
}

function DropdownMenuItem({ className, ...props }: React.ComponentProps<typeof Menu.Item>) {
    return (
        <Menu.Item
            className={cn(
                'block w-full cursor-pointer px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-wide text-gray-600 outline-none transition-colors',
                'hover:bg-gray-50 hover:text-action data-[highlighted]:bg-gray-50 data-[highlighted]:text-action',
                className,
            )}
            {...props}
        />
    );
}

function DropdownMenuSeparator({ className, ...props }: React.ComponentProps<typeof Menu.Separator>) {
    return <Menu.Separator className={cn('h-px bg-gray-100', className)} {...props} />;
}

export { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator };
