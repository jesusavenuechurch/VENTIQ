<?php

namespace App\Livewire\Assist;

use App\Models\AssistConversation;
use Livewire\Component;

class ChatPage extends Component
{
    public ?AssistConversation $conversation = null;
    public string $input = '';

    // Embedded on every page (see layouts/app.blade.php), not a route —
    // gated there to org users only, so no superadmin/organization_id guard
    // needed here. Picks up the user's own most recent conversation rather
    // than always starting fresh, since mount() now fires on every page
    // load instead of once per explicit visit to a dedicated page.
    public function mount(): void
    {
        $this->conversation = app(\App\Services\Assist\AssistChat::class)->conversation(auth()->user());
    }

    public function getPollingIntervalProperty(): ?string
    {
        // Only poll while an answer is still being worked out.
        return $this->conversation->messages()->where('status', 'pending')->exists() ? '2s' : null;
    }

    public function sendMessage(): void
    {
        $this->validate(['input' => 'required|string|max:4000']);
        app(\App\Services\Assist\AssistChat::class)->ask($this->conversation, auth()->user(), $this->input);

        $this->input = '';
        $this->dispatch('scroll-to-bottom');
    }

    public function poll(): void
    {
        app(\App\Services\Assist\AssistChat::class)->refresh($this->conversation);
    }

    public function render()
    {
        return view('livewire.assist.chat-page', [
            'messages' => $this->conversation->messages,
        ]);
    }
}
