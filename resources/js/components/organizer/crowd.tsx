import { avatarUrl } from '@/lib/avatar';
import { cn } from '@/lib/utils';

/** A little group of faces for empty and happy states. */
export function Crowd({ seeds }: { seeds: string[] }) {
    return (
        <div className="mb-4 flex justify-center -space-x-3" aria-hidden>
            {seeds.map((seed, i) => (
                <img key={seed} src={avatarUrl(seed)} alt="" className={cn('shrink-0 rounded-full bg-slate-100 ring-4 ring-white', i === 1 ? 'relative z-10 -mt-2 size-16' : 'size-12')} />
            ))}
        </div>
    );
}
