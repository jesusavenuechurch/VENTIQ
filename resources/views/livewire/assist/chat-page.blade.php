<div wire:poll.{{ $this->pollingInterval ?? '10s' }}="poll" class="flex flex-col h-full">
    <div class="flex-1 overflow-y-auto p-5 space-y-3" x-data x-on:scroll-to-bottom.window="$el.scrollTop = $el.scrollHeight">
        @forelse($messages as $msg)
            <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-[13px] {{ $msg->role === 'user' ? 'bg-[#1D4069] text-white' : 'bg-gray-50 text-gray-800' }}">
                    @if($msg->status === 'pending')
                        <span class="flex gap-1 py-1">
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
                        </span>
                    @else
                        <div class="prose prose-sm max-w-none">{!! Str::markdown($msg->content ?? '') !!}</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="h-full flex flex-col items-center justify-center text-center px-4">
                <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.2em] mb-2">Ask Ventiq</p>
                <p class="text-[12px] text-gray-400 max-w-[220px]">
                    "How many meetings this year?" · "What came up about procurement?"
                </p>
            </div>
        @endforelse
    </div>

    <form wire:submit="sendMessage" class="border-t bg-white p-3 flex gap-2 shrink-0">
        <input wire:model="input" type="text" placeholder="Ask about your sessions..."
               class="flex-1 min-w-0 rounded-full border border-gray-200 px-4 py-2 text-[13px] outline-none focus:ring-2 focus:ring-[#F07F22]/20" autofocus>
        <button type="submit" class="shrink-0 bg-[#F07F22] text-white rounded-full w-9 h-9 flex items-center justify-center hover:bg-[#1D4069] transition-colors">
            <i class="fas fa-arrow-up text-xs"></i>
        </button>
    </form>
</div>
