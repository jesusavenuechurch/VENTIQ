import { Input } from '@/components/ui/input';

/** The 8 local digits, from whatever was typed or pasted ("+266 5949 4756" too). */
export function localDigits(value: string): string {
    let digits = value.replace(/\D/g, '');
    if (digits.length > 8 && digits.startsWith('266')) digits = digits.slice(3);
    return digits.slice(0, 8);
}

/** "59494756" shown as "5949 4756". */
export function formatLocal(value: string): string {
    const digits = localDigits(value);
    return digits.length > 4 ? `${digits.slice(0, 4)} ${digits.slice(4)}` : digits;
}

/**
 * A Lesotho number: +266 shown in front, the local 8 digits typed. The
 * value kept is the digits alone; the server adds +266.
 */
export function PhoneInput({
    id,
    value,
    onChange,
    invalid,
    required,
}: {
    id: string;
    value: string;
    onChange: (digits: string) => void;
    invalid?: boolean;
    required?: boolean;
}) {
    return (
        <div className="flex items-center gap-2">
            <span className="rounded-2xl bg-slate-100 px-4 py-4 text-xs font-black text-gray-400">+266</span>
            <Input
                id={id}
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                placeholder="5949 4756"
                required={required}
                value={formatLocal(value)}
                onChange={(e) => onChange(localDigits(e.target.value))}
                aria-invalid={invalid}
            />
        </div>
    );
}
