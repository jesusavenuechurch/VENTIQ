<div>
{{-- Listener for open event --}}
<div x-data x-on:open-ventiq-assist-modal.window="$wire.openModal()"></div>

@if($open)
<div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70" wire:click="closeModal"></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-2xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-white/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#1D4069] flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">Ventiq Assist</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Tell me about your event and I'll fill in the details</p>
                </div>
            </div>
            <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable body --}}
        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

            {{-- Poll while generating --}}
            @if($generating)
                <div wire:poll.2000ms="polling"></div>
            @endif

            {{-- GENERATING STATE --}}
            @if($generating)
            <div class="py-10 text-center space-y-4">
                <div class="flex justify-center">
                    <div class="w-14 h-14 rounded-full bg-[#1D4069]/10 flex items-center justify-center">
                        <svg class="animate-spin w-7 h-7 text-[#1D4069]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"></path>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">Writing your event content...</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">This takes a just few seconds. You can wait here.</p>
                </div>
                <div class="flex justify-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#1D4069] animate-bounce" style="animation-delay:0ms"></span>
                    <span class="w-2 h-2 rounded-full bg-[#1D4069] animate-bounce" style="animation-delay:150ms"></span>
                    <span class="w-2 h-2 rounded-full bg-[#1D4069] animate-bounce" style="animation-delay:300ms"></span>
                </div>
            </div>
            @endif

            {{-- INPUT FORM --}}
            @if(!$generating && !$generated)
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                        Event Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" wire:model="eventName"
                        placeholder="e.g. Youth Prayer Night, Annual Business Summit"
                        class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1D4069]"/>
                    @error('eventName') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                            Category <span class="text-red-500">*</span>
                        </label>
                        <select wire:model="category"
                            class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1D4069]">
                            <option value="">Select category...</option>
                            <option value="Church / Spiritual">⛪ Church / Spiritual</option>
                            <option value="Conference / Seminar">🎤 Conference / Seminar</option>
                            <option value="Corporate / Business">💼 Corporate / Business</option>
                            <option value="Workshop / Training">📚 Workshop / Training</option>
                            <option value="Youth Event">🌟 Youth Event</option>
                            <option value="Fundraiser / Charity">❤️ Fundraiser / Charity</option>
                            <option value="Music / Entertainment">🎵 Music / Entertainment</option>
                            <option value="Community / Cultural">🏘️ Community / Cultural</option>
                            <option value="Sports / Fitness">⚽ Sports / Fitness</option>
                            <option value="School / Academic">🎓 School / Academic</option>
                            <option value="Government / Official">🏛️ Government / Official</option>
                            <option value="Private / Social">🎉 Private / Social</option>
                        </select>
                        @error('category') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Tone</label>
                        <select wire:model="tone"
                            class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1D4069]">
                            <option value="professional">Professional</option>
                            <option value="exciting and energetic">Exciting & Energetic</option>
                            <option value="spiritual and uplifting">Spiritual & Uplifting</option>
                            <option value="formal and corporate">Formal & Corporate</option>
                            <option value="warm and community-focused">Warm & Community</option>
                            <option value="youthful and fun">Youthful & Fun</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Venue</label>
                        <input type="text" wire:model="venue" placeholder="e.g. Maseru City Hall"
                            class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1D4069]"/>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Target Audience</label>
                        <input type="text" wire:model="audience" placeholder="e.g. Youth, Business professionals"
                            class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1D4069]"/>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                        Extra Notes <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea wire:model="notes" rows="2"
                        placeholder="Speakers, themes, dress code, anything special..."
                        class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1D4069] resize-none"></textarea>
                </div>

                @if($error)
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    ⚠️ {{ $error }}
                </div>
                @endif

                <button type="button" wire:click="generate"
                    class="w-full py-3 px-4 bg-[#1D4069] hover:bg-[#153150] text-white text-sm font-bold rounded-xl transition-all inline-flex items-center justify-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                    </svg>
                    Generate with Ventiq Assist
                </button>
            </div>
            @endif

            {{-- RESULTS --}}
            @if($generated)
            <div class="space-y-4">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Ready to fill into your event form
                </p>

                @if(count($titles) > 0)
                <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10">
                    <p class="text-xs font-medium text-gray-400 mb-2">Suggested name (pick one or keep yours)</p>
                    <div class="space-y-1.5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="selectedTitle" value="{{ $eventName }}" class="text-[#1D4069]"/>
                            <span class="text-sm text-gray-900 dark:text-white">{{ $eventName }} <span class="text-xs text-gray-400">(original)</span></span>
                        </label>
                        @foreach($titles as $title)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="selectedTitle" value="{{ $title }}" class="text-[#1D4069]"/>
                            <span class="text-sm text-gray-900 dark:text-white">{{ $title }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($tagline)
                <div class="p-3 bg-[#1D4069]/5 border border-[#1D4069]/20 rounded-xl">
                    <p class="text-xs font-medium text-[#1D4069] dark:text-blue-300 mb-1">Tagline</p>
                    <p class="text-sm text-gray-900 dark:text-white italic">"{{ $tagline }}"</p>
                </div>
                @endif

                @if($description)
                <div class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10">
                    <p class="text-xs font-medium text-gray-400 mb-1">Description preview</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300 line-clamp-3">{{ $description }}</p>
                </div>
                @endif

                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/30 rounded-xl">
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        💬 WhatsApp message, Facebook caption and hashtags have also been generated.
                        You can access them from the event edit page after saving.
                    </p>
                </div>

                <button type="button" wire:click="fillEventForm"
                    class="w-full py-3 px-4 bg-[#1D4069] hover:bg-[#153150] text-white text-sm font-bold rounded-xl transition-all inline-flex items-center justify-center gap-2 shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Fill My Event Form
                </button>

                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Not quite right? Adjust and regenerate:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach([
                            ['tone' => 'more professional and formal',     'label' => 'More Professional'],
                            ['tone' => 'more exciting and energetic',      'label' => 'More Exciting'],
                            ['tone' => 'more spiritual and uplifting',     'label' => 'More Spiritual'],
                            ['tone' => 'shorter and more concise',         'label' => 'Shorter'],
                            ['tone' => 'warmer and more community-focused','label' => 'Warmer'],
                        ] as $adj)
                        <button type="button" wire:click="regenerate('{{ $adj['tone'] }}')"
                            class="text-xs px-3 py-1.5 border border-gray-200 dark:border-white/10 rounded-full text-gray-600 dark:text-gray-300 hover:border-[#1D4069] hover:text-[#1D4069] transition-colors">
                            {{ $adj['label'] }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <button type="button" wire:click="resetAssist"
                    class="w-full py-2 text-xs text-gray-500 hover:text-gray-700 border border-dashed border-gray-300 dark:border-white/10 rounded-xl transition-colors">
                    ← Start over
                </button>
            </div>
            @endif

        </div>
    </div>
</div>
@endif
</div>