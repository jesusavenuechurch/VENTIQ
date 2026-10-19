import { ImageIcon, Upload } from 'lucide-react';
import { useState } from 'react';

/**
 * The organization's logo: a preview and an upload button, with "Remove"
 * when one is on file. Hand back the chosen file and the remove choice.
 */
export function LogoField({
    current,
    file,
    remove,
    onFile,
    onRemove,
    error,
    editable = true,
    label = 'Logo',
}: {
    current: string | null;
    file: File | null;
    remove?: boolean;
    onFile: (file: File | null) => void;
    onRemove?: (remove: boolean) => void;
    error?: string;
    editable?: boolean;
    label?: React.ReactNode;
}) {
    const [preview, setPreview] = useState(current);
    const shown = preview && !remove;

    return (
        <div>
            <span className="mb-2 ml-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">{label}</span>
            <div className="flex flex-wrap items-center gap-4">
                <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50">
                    {shown ? <img src={preview} alt="" className="size-full object-contain" /> : <ImageIcon className="size-7 text-slate-300" />}
                </div>
                {editable && (
                    <div className="min-w-0 space-y-1.5">
                        <label htmlFor="logo" className="inline-flex cursor-pointer items-center gap-2 rounded-full bg-action px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white hover:bg-action-ink">
                            <Upload className="size-3.5" />{preview ? 'Change logo' : 'Upload logo'}
                        </label>
                        <p className="truncate text-[11px] text-gray-400">{file?.name || 'PNG, JPG or WebP, up to 4 MB'}</p>
                        {current && onRemove && (
                            <label className="flex cursor-pointer items-center gap-2 text-[11px] font-bold text-gray-500">
                                <input type="checkbox" checked={!!remove} onChange={(e) => onRemove(e.target.checked)} className="rounded" /> Remove the logo
                            </label>
                        )}
                    </div>
                )}
            </div>
            <input
                id="logo"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={(e) => {
                    const chosen = e.target.files?.[0] ?? null;
                    onFile(chosen);
                    setPreview(chosen ? URL.createObjectURL(chosen) : current);
                }}
            />
            {error && <p className="ml-1 mt-1 text-[11px] font-bold text-rose-500">{error}</p>}
        </div>
    );
}
