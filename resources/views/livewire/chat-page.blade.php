{{-- resources/views/livewire/assist/chat-page.blade.php --}}
<div wire:poll.{{ $this->pollingInterval ?? '10s' }}="poll" class="flex flex-col h-screen bg-gray-50">
    <div class="flex-1 overflow-y-auto p-6 space-y-4" x-data x-on:scroll-to-bottom.window="$el.scrollTop = $el.scrollHeight">
        @foreach($messages as $msg)
            <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-lg rounded-2xl px-4 py-3 {{ $msg->role === 'user' ? 'bg-[#1D4069] text-white' : 'bg-white border' }}">
                    @if($msg->status === 'pending')
                        <span class="flex gap-1">
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
                        </span>
                    @else
                        <div class="prose prose-sm">{!! Str::markdown($msg->content ?? '') !!}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit="sendMessage" class="border-t bg-white p-4 flex gap-2">
        <input wire:model="input" type="text" placeholder="Ask Ventiq Assist anything..."
               class="flex-1 rounded-full border px-4 py-2" autofocus>
        <button type="submit" class="bg-[#F07F22] text-white rounded-full px-5 py-2 font-semibold">Send</button>
    </form>
</div>