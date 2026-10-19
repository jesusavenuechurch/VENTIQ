import { ArrowUp, Sparkles, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

interface Message {
    id: number;
    role: 'user' | 'assistant';
    status: 'pending' | 'completed' | 'failed';
    html: string | null;
    text: string | null;
}

/**
 * Ask Ventiq: a docked panel, not a page, so a quick question doesn't cost
 * your place in what you were doing. Its conversation carries on across
 * pages; while an answer is being worked out it checks every 2 seconds.
 */
export function AskVentiq({ raised }: { raised: boolean }) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const [pending, setPending] = useState(false);
    const [input, setInput] = useState('');
    const list = useRef<HTMLDivElement>(null);

    const load = useCallback(async (init?: RequestInit) => {
        const res = await fetch(route(init ? 'assist.ask' : 'assist.messages'), {
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '' },
            ...init,
        });
        if (!res.ok) return;
        const data: { messages: Message[]; pending: boolean } = await res.json();
        setMessages(data.messages);
        setPending(data.pending);
    }, []);

    useEffect(() => {
        if (open) void load();
    }, [open, load]);

    useEffect(() => {
        if (!open || !pending) return;
        const t = window.setInterval(() => void load(), 2000);
        return () => window.clearInterval(t);
    }, [open, pending, load]);

    useEffect(() => {
        list.current?.scrollTo({ top: list.current.scrollHeight });
    }, [messages]);

    async function ask(e: React.FormEvent) {
        e.preventDefault();
        const text = input.trim();
        if (!text) return;
        setInput('');
        await load({ method: 'POST', body: JSON.stringify({ text }) });
    }

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-label={open ? 'Close Ask Ventiq' : 'Ask Ventiq'}
                className={cn('fixed right-6 z-[55] flex size-14 items-center justify-center rounded-full bg-brand text-white shadow-xl transition-all hover:bg-action active:scale-95', raised ? 'bottom-20 sm:bottom-6' : 'bottom-6')}
            >
                {open ? <X className="size-5" /> : <Sparkles className="size-5" />}
            </button>

            {open && (
                <div className={cn('fixed right-6 z-[55] flex h-[520px] max-h-[70vh] w-[calc(100vw-3rem)] max-w-sm flex-col overflow-hidden rounded-[2rem] border border-gray-100 bg-white shadow-2xl animate-in fade-in slide-in-from-bottom-3', raised ? 'bottom-36 sm:bottom-24' : 'bottom-24')}>
                    <div className="flex shrink-0 items-center justify-between border-b px-5 py-4">
                        <div>
                            <p className="text-[13px] font-black uppercase tracking-tight text-brand">Ask Ventiq</p>
                            <p className="text-[9px] font-bold uppercase tracking-widest text-gray-400">Search your sessions</p>
                        </div>
                        <button type="button" onClick={() => setOpen(false)} className="rounded-full p-2 text-gray-300 transition-colors hover:bg-gray-50" aria-label="Close"><X className="size-4" /></button>
                    </div>

                    <div ref={list} className="flex-1 space-y-3 overflow-y-auto p-5">
                        {messages.length === 0 ? (
                            <div className="flex h-full flex-col items-center justify-center px-4 text-center">
                                <p className="mb-2 text-[10px] font-black uppercase tracking-[0.2em] text-gray-300">Ask Ventiq</p>
                                <p className="max-w-[220px] text-[12px] text-gray-400">"How many meetings this year?" · "What came up about procurement?"</p>
                            </div>
                        ) : (
                            messages.map((m) => (
                                <div key={m.id} className={cn('flex', m.role === 'user' ? 'justify-end' : 'justify-start')}>
                                    <div className={cn('max-w-[85%] rounded-2xl px-4 py-2.5 text-[13px]', m.role === 'user' ? 'bg-brand text-white' : 'bg-gray-50 text-gray-800')}>
                                        {m.status === 'pending' ? (
                                            <span className="flex gap-1 py-1">
                                                {[0, 150, 300].map((d) => <span key={d} className="size-1.5 animate-bounce rounded-full bg-gray-400" style={{ animationDelay: `${d}ms` }} />)}
                                            </span>
                                        ) : m.html ? (
                                            // The server renders the answer's Markdown, escaping any HTML in it.
                                            <div className="space-y-2 [&_a]:underline [&_li]:ml-4 [&_ol]:list-decimal [&_ul]:list-disc" dangerouslySetInnerHTML={{ __html: m.html }} />
                                        ) : (
                                            <p className="whitespace-pre-line">{m.text}</p>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    <form onSubmit={ask} className="flex shrink-0 gap-2 border-t bg-white p-3">
                        <input autoFocus value={input} onChange={(e) => setInput(e.target.value)} placeholder="Ask about your sessions..." className="min-w-0 flex-1 rounded-full border border-gray-200 px-4 py-2 text-[13px] outline-none focus:ring-2 focus:ring-action/20" />
                        <button type="submit" className="flex size-9 shrink-0 items-center justify-center rounded-full bg-action text-white transition-colors hover:bg-brand" aria-label="Send"><ArrowUp className="size-4" /></button>
                    </form>
                </div>
            )}
        </>
    );
}
