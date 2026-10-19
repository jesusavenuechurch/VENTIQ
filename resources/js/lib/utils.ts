import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** M1,234.50: how VENTIQ shows money. */
export function money(value: number | string | null | undefined): string {
    const amount = Number(value ?? 0);
    return 'M' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
