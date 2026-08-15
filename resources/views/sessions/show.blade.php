<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Ventiq — {{ $session->resolved_title }}</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  :root{
    --ink: #0f172a;
    --muted: #94a3b8;
    --muted-light: #cbd5e1;
    --bg-light: #f8fafc;
    --line: #e2e8f0;
    --gold: #D4AF37;
    --emerald: #10b981;
    --brand-orange: #F07F22;
  }
  * { scrollbar-width: none; }
  *::-webkit-scrollbar { display: none; }
  body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #000; color: var(--ink); }

  .label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 2px; }
  .value { font-weight: 800; color: var(--ink); text-transform: uppercase; }
  .badge-pill { padding: 5px 12px; border-radius: 999px; color: #fff; font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 1.5px; display: inline-block; }

  .card { background: #fff; border-radius: 40px; overflow: hidden; position: relative; box-shadow: 0 40px 100px rgba(0,0,0,0.5); }
  .hole { position: absolute; width: 30px; height: 30px; background: #000; border-radius: 50%; z-index: 30; }
  .hole-top { top: -15px; } .hole-bottom { bottom: -15px; }

  .watermark {
    position: absolute; bottom: -20px; left: -10px;
    font-size: 130px; font-weight: 900; font-style: italic;
    color: rgba(15,23,42,0.03); text-transform: uppercase;
    letter-spacing: -4px; pointer-events: none; user-select: none;
  }

  .spiral-strip {
    position: absolute; left: 0; top: 0; bottom: 0; width: 18px;
    background-image: radial-gradient(circle at center, #fff 0 3px, var(--muted-light) 3px 4px, transparent 4px);
    background-size: 100% 28px; background-repeat: repeat-y;
    opacity: .8; z-index: 5;
  }

  @keyframes breathe {
    0%,100% { transform: scale(1); opacity: .6; } 50% { transform: scale(1.7); opacity: 0; }
  }
  .breathe-ring::before {
    content:''; position:absolute; inset:-3px; border-radius:9999px;
    border: 1.5px solid var(--gold); animation: breathe 2s ease-out infinite;
  }

  @keyframes wave { 0%,100%{height:3px;} 50%{height:14px;} }
  .wave-bar { width: 2.5px; background: var(--gold); border-radius: 2px; }

  .sticky {
    position: relative; border-radius: 6px 6px 10px 10px; padding: 16px 14px 14px;
    transform: rotate(var(--r, -1.5deg));
    box-shadow: 0 8px 18px rgba(15,23,42,0.10), 0 1px 0 rgba(15,23,42,0.05);
  }
  .sticky::after {
    content: ''; position: absolute; bottom: 0; right: 0; width: 16px; height: 16px;
    background: linear-gradient(135deg, transparent 50%, rgba(15,23,42,0.10) 50%);
    border-radius: 0 0 10px 0;
  }
  .tape {
    position: absolute; top: -9px; left: 50%; width: 46px; height: 16px;
    background: rgba(255,255,255,0.55); border: 1px solid rgba(255,255,255,0.7);
    transform: translateX(-50%) rotate(-3deg); backdrop-filter: blur(1px);
    box-shadow: 0 2px 4px rgba(15,23,42,0.08);
  }

  @keyframes stickerIn {
    0% { opacity:0; transform: translateY(8px) rotate(0deg) scale(.92); }
    100% { opacity:1; transform: translateY(0) rotate(var(--r,-1.5deg)) scale(1); }
  }
  .sticker-in { animation: stickerIn .45s cubic-bezier(.2,.9,.3,1.2) both; }

  @keyframes bounceDot { 0%,80%,100%{opacity:.3; transform:translateY(0);} 40%{opacity:1; transform:translateY(-3px);} }
  .bounce-dot { animation: bounceDot 1.2s ease-in-out infinite; }

  .dashed-v { border-left: 2px dashed var(--line); }
  .dashed-v-r { border-right: 2px dashed var(--line); }

  .topic-input {
    background: transparent; border: none; outline: none; width: 100%;
    font-size: 15px; font-weight: 600; color: var(--muted);
    border-bottom: 1px dashed transparent;
  }
  .topic-input:focus { color: var(--ink); border-bottom-color: var(--muted-light); }

  /* ============ THE FIX ============
     The old <input> scrolled horizontally because (a) inputs never wrap,
     and (b) it lived in a flex row without min-width:0, so even a wrapping
     element would have been pushed wide instead of shrinking to fit.
     Both are addressed below. */
  .draft-row {
    display: flex;
    gap: 1rem;
    align-items: flex-start; /* was items-baseline — wrong once the textarea grows past 1 line */
    min-width: 0;            /* <-- lets the flex child actually shrink/wrap instead of overflowing */
  }
  .draft-textarea {
    display: block;
    width: 100%;
    min-width: 0;
    flex: 1 1 auto;
    background: transparent;
    border: none;
    outline: none;
    resize: none;
    overflow: hidden;           /* no inner scrollbar, height grows instead */
    white-space: pre-wrap;      /* wrap like a notebook line, never scroll sideways */
    word-break: break-word;
    font-size: 15px;
    font-style: italic;
    line-height: 1.6;
    color: var(--ink);
    caret-color: var(--gold);
    font-family: inherit;
  }
  .draft-textarea:disabled {
    opacity: .45;
    caret-color: transparent;
  }

  /* ============ PAGE FLIP ============ */
  @keyframes pageFlipOut {
    0%   { transform: translateX(0) rotate(0deg);   opacity: 1; }
    100% { transform: translateX(-24px) rotate(-1.5deg); opacity: 0; }
  }
  @keyframes pageFlipIn {
    0%   { transform: translateX(24px) rotate(1deg); opacity: 0; }
    100% { transform: translateX(0) rotate(0deg);     opacity: 1; }
  }
  .page-flip-out { animation: pageFlipOut 240ms ease-in forwards; }
  .page-flip-in  { animation: pageFlipIn 240ms ease-out; }
</style>
</head>
<body class="h-full overflow-hidden">
<div class="h-full flex items-center justify-center p-6 lg:p-10" x-data="workspace()">
  <div class="card w-full h-full max-w-[1500px] flex">

    <div class="hole hole-top" style="right: 300px;"></div>
    <div class="hole hole-bottom" style="right: 300px;"></div>

    <!-- ===================== LEFT — ROSTER ===================== -->
    <aside class="w-60 shrink-0 h-full flex flex-col dashed-v-r" style="background: var(--bg-light);">
      <div class="px-5 pt-7 pb-5">
        <p class="text-[14px] font-black tracking-tight" style="letter-spacing:-0.5px;">VENTIQ</p>
        <p class="label mt-2">Live Session</p>
        <p class="text-[11px] font-bold uppercase mt-0.5" style="color: var(--muted);">{{ $session->resolved_title }}</p>
      </div>

      <div class="flex-1 overflow-y-auto px-3 pb-4 space-y-5">
        @if($session->event_id)
          <div class="rounded-2xl p-4 mx-0" style="background: #fff; border: 2px solid var(--brand-orange);">
            <div class="flex items-center justify-between mb-1">
              <p class="text-[10px] font-black uppercase tracking-widest" style="color: var(--ink);">Participants</p>
              <span class="text-[13px] font-black" style="color: var(--ink);"
                    x-text="participantCount + (expectedParticipants ? ' / ' + expectedParticipants : '')"></span>
            </div>
            <p class="text-[9.5px] font-semibold leading-snug mb-3" style="color: var(--muted);">
              Share the QR or link so people can check themselves in
            </p>
            <div class="grid grid-cols-2 gap-2">
              <a href="{{ route('sessions.checkin.pass', $session) }}" target="_blank"
                 class="flex flex-col items-center justify-center gap-1 py-3 rounded-xl text-white font-black text-[10px] uppercase tracking-wide transition hover:opacity-90"
                 style="background: var(--brand-orange);">
                <span style="font-size: 17px; line-height: 1;">▦</span>
                <span>Show QR</span>
              </a>
              <button type="button" @click="copyCheckinLink()"
                      class="flex flex-col items-center justify-center gap-1 py-3 rounded-xl font-black text-[10px] uppercase tracking-wide text-white transition hover:opacity-90"
                      :style="linkCopied ? 'background: var(--emerald);' : 'background: var(--ink);'">
                <span style="font-size: 17px; line-height: 1;" x-text="linkCopied ? '✓' : '⛓'"></span>
                <span x-text="linkCopied ? 'Copied!' : 'Copy Link'"></span>
              </button>
            </div>
            <a href="{{ route('sessions.checkin', $session) }}"
               class="block text-center mt-3 text-[10px] font-bold underline" style="color: var(--muted);" target="_blank">View Roster →</a>
          </div>
        @endif
        <!-- PRESENTING — capped height, scrolls once it grows past ~6 rows -->
<div x-show="speakers.some(s => s.isPresenting !== false)">
  <p class="label px-2 mb-2">Presenting</p>
  <div class="space-y-1 overflow-y-auto" style="max-height: 280px;">
    <template x-for="group in ['active','upcoming','completed']" :key="group">
      <template x-for="speaker in speakers.filter(s => s.isPresenting !== false && s.status === group)" :key="speaker.id">
        <div @click="focusSpeaker(speaker)"
             class="flex items-center gap-2.5 px-2 py-2 rounded-2xl cursor-pointer transition mb-1"
             :class="selectedSpeaker.id === speaker.id ? 'bg-white shadow-md' : 'hover:bg-white/70'">
          <div class="relative shrink-0" :class="speaker.status === 'active' ? 'breathe-ring' : ''">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black text-white ring-2 ring-white shadow-sm"
                 :style="`background:${speaker.color}`" x-text="speaker.initials"></div>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-[11.5px] font-black uppercase truncate" :class="speaker.status==='completed' ? 'line-through opacity-40' : ''" x-text="speaker.name"></p>
            <span x-show="speaker.status==='active'" class="badge-pill mt-1" style="background: var(--gold);">Live</span>
          </div>
          <span class="text-[9px] font-bold shrink-0" style="color: var(--muted);" x-text="speaker.status==='completed' ? '✓' : formatTime(speaker.duration)"></span>
        </div>
      </template>
    </template>
  </div>
</div>

<!-- NON-PRESENTING — no timer, no live badge, own scroll cap -->
<div x-show="speakers.some(s => s.isPresenting === false)">
  <p class="label px-2 mb-2 mt-4">Not Presenting</p>
  <div class="space-y-1 overflow-y-auto" style="max-height: 200px;">
    <template x-for="speaker in speakers.filter(s => s.isPresenting === false)" :key="speaker.id">
      <div @click="focusSpeaker(speaker)"
           class="flex items-center gap-2.5 px-2 py-2 rounded-2xl cursor-pointer transition mb-1"
           :class="selectedSpeaker.id === speaker.id ? 'bg-white shadow-md' : 'hover:bg-white/70'">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black text-white ring-2 ring-white shadow-sm"
             :style="`background:${speaker.color}`" x-text="speaker.initials"></div>
        <div class="min-w-0 flex-1">
          <p class="text-[11.5px] font-black uppercase truncate" x-text="speaker.name"></p>
          <span class="text-[8px] font-bold text-gray-300 uppercase tracking-wide" x-text="speaker.role || 'Non-presenting'"></span>
        </div>
      </div>
    </template>
  </div>
</div>
  <div class="px-2 mt-2">
    <button type="button" x-show="!showAddPresenter" @click="showAddPresenter = true; $nextTick(() => $refs.newPresenterInput?.focus())"
            class="w-full text-left label" style="cursor:pointer; background:none; border:none; padding:8px 0;">
            + Add Person
          </button>
          <div x-show="showAddPresenter" class="flex gap-1.5 mt-1">
            <input x-ref="newPresenterInput" x-model="newPresenterName"
                  @keydown.enter="addPresenter()" @keydown.escape="showAddPresenter = false; newPresenterName = ''"
                  placeholder="Name…" class="topic-input text-[12px]" style="background:#fff; border-radius:8px; padding:6px 10px;">
            <select x-model="newPresenterRole" class="topic-input text-[11px]" style="background:#fff; border-radius:8px; padding:6px 8px; max-width:100px;">
              <template x-for="opt in roleOptions" :key="opt.value">
                <option :value="opt.value" x-text="opt.label + (opt.presenting ? '' : ' (non-presenting)')"></option>
              </template>
            </select>
            <label class="flex items-center gap-1 text-[9px] font-black text-gray-500 uppercase" style="white-space:nowrap;">
              <input type="checkbox" x-model="newPresenterPresenting" class="accent-[#1D4069]"> Presenting
            </label>
            <button type="button" @click="addPresenter()" class="label" style="cursor:pointer; background:none; border:none;">✓</button>
          </div>
        </div>
      </div>

      <div class="px-5 py-4 flex items-center justify-between" style="border-top: 2px dashed var(--line);">
        <span class="label" x-text="`${completedCount}/${speakers.length} done`"></span>
        <span class="text-[10px] font-black" style="color: var(--ink);" x-text="formatTime(globalClock)"></span>
      </div>
    </aside>

    <!-- ===================== CENTER — NOTEBOOK ===================== -->
    <main class="flex-1 h-full flex flex-col relative overflow-hidden">
      <div class="spiral-strip"></div>
      <div class="watermark">Ventiq</div>

      <header class="relative z-10 pl-14 lg:pl-16 pr-10 lg:pr-14 pt-9 pb-5 flex items-start justify-between">
        <div class="flex-1 min-w-0">
          <span class="badge-pill mb-3"
                :style="`background:${paused ? 'var(--gold)' : (isLive ? 'var(--gold)' : 'var(--muted-light)')}`"
                x-text="paused ? (pauseReason === 'auto' ? 'Away from page' : 'Paused') : (isLive ? 'Capturing' : 'Ready')"></span>
          <h1 class="value text-[28px] leading-none mt-3" style="letter-spacing:-1px;"
            x-text="selectedSpeaker.name || (isLive ? 'Add a presenter to begin' : 'Select a presenter')"></h1>
          <input x-show="selectedSpeaker.id" x-model="selectedSpeaker.topic"
                 class="topic-input mt-1.5" placeholder="Untitled — click to name this presentation">
          @if($session->event_id)
            <a href="{{ route('sessions.checkin', $session) }}" class="label mt-2 inline-block hover:underline" target="_blank">Check-in Desk →</a>
          @endif
        </div>
        <div class="flex items-center gap-4 shrink-0 mt-1">
          <!-- quiet page nav — flip back to review, flip forward to catch back up -->
          <div class="flex items-center gap-2" x-show="isLive && selectedSpeaker.pageBreaks">
            <button type="button" @click="goToPreviousPage()" x-show="selectedSpeaker.viewPageIndex > 0"
                    class="label" style="cursor:pointer; background:none; border:none; padding:0;">‹</button>
            <span class="label" x-text="`Page ${selectedSpeaker.viewPageIndex + 1} of ${selectedSpeaker.pageBreaks ? selectedSpeaker.pageBreaks.length : 1}`"></span>
            <button type="button" @click="goToNextPage()" x-show="selectedSpeaker.pageBreaks && selectedSpeaker.viewPageIndex < selectedSpeaker.pageBreaks.length - 1"
                    class="label" style="cursor:pointer; background:none; border:none; padding:0;">›</button>
          </div>
          <!-- pause / resume — manual control, shares state with the auto-pause below -->
          <button x-show="isLive && selectedSpeaker.id === liveSpeakerId"
                  @click="togglePause()"
                  class="badge-pill transition hover:opacity-80"
                  :class="paused && pauseReason === 'auto' ? 'cursor-default' : 'cursor-pointer'"
                  :style="`background: ${paused ? 'var(--muted-light)' : 'var(--ink)'}`"
                  x-text="paused ? (pauseReason === 'auto' ? 'Away' : 'Resume →') : 'Pause'">
          </button>
          <button x-show="isLive && selectedSpeaker.id === liveSpeakerId && !paused"
                  @click="finishSegment()"
                  class="badge-pill cursor-pointer transition hover:opacity-80"
                  style="background: var(--ink);"
                  x-text="isLastSpeaker ? 'Finish Segment →' : 'Next Speaker →'">
          </button>
        </div>
      </header>

      <!-- the notebook page itself: overflow-hidden, no scrollbar, contents get flipped out/in -->
      <div x-ref="notesArea"
           class="relative z-10 flex-1 overflow-hidden pl-14 lg:pl-16 pr-10 lg:pr-14 pb-6"
           :class="flipping ? 'page-flip-out' : 'page-flip-in'">
        <div class="max-w-2xl">

          <template x-if="!isLive && !speakers.some(s=>s.logs.length)">
            <div class="pt-16">
              <p class="text-[16px] italic" style="color: var(--muted);">The page is empty. Start the session and write as they speak.</p>
              <button @click="startSession()" class="badge-pill mt-5 cursor-pointer" style="background: var(--emerald);">
                Begin Capturing
              </button>
            </div>
          </template>

          <template x-if="!isLive && speakers.length > 0 && completedCount === speakers.length">
            <div class="pt-16">
              <p class="text-[16px] italic" style="color: var(--muted);">Every presenter has finished.</p>
              <a href="/sessions/{{ $session->id }}/report" class="badge-pill mt-5 inline-block" style="background: var(--ink);">
                View Report →
              </a>
            </div>
          </template>

          <!-- only the CURRENT page's committed lines render here -->
          <template x-for="log in visibleLogs" :key="log.id">
            <div class="py-3 flex gap-4 items-baseline" style="border-bottom: 1px dashed var(--line);">
              <span class="text-[10px] font-black shrink-0 w-10" style="color: var(--muted-light);" x-text="log.time"></span>
              <p class="text-[15px] leading-relaxed" style="color: var(--ink); white-space: pre-wrap; word-break: break-word;" x-text="log.text"></p>
            </div>
          </template>

          <!-- shown only while looking at an OLD page of the presenter currently being captured —
               typing must not happen here, so the draft line below is hidden and this takes its place -->
          <div x-show="isLive && selectedSpeaker.id === liveSpeakerId && !isViewingLatestPage(selectedSpeaker)"
               class="py-3 flex items-center gap-3">
            <span class="label" style="color: var(--gold);">Reviewing an earlier page</span>
            <button type="button" @click="returnToCurrentPage()" class="label"
                    style="text-decoration: underline; cursor: pointer; background: none; border: none; padding: 0;">
              Return to current page →
            </button>
          </div>

          <!-- quiet nudge before the page actually fills — a heads-up, not an interruption -->
          <p x-show="isLive && selectedSpeaker.id === liveSpeakerId && isViewingLatestPage(selectedSpeaker) && pageFillRatio > 0.82"
             class="label" style="color: var(--gold);">Page filling up — next line may turn the page</p>

          <!-- paused banner — replaces the draft line while paused so it's obvious nothing is being captured -->
          <div x-show="isLive && selectedSpeaker.id === liveSpeakerId && paused && isViewingLatestPage(selectedSpeaker)"
               class="py-3">
            <p class="label" style="color: var(--muted);"
               x-text="pauseReason === 'auto' ? 'Paused automatically — this tab is in the background' : 'Paused — resume when ready'"></p>
          </div>

          <!-- the live, uncommitted line — wraps in place, never scrolls sideways.
               Only rendered on the current page and only while not paused: if the
               user has flipped back to review, or the session is paused, this hides
               and the relevant banner above takes its place instead. -->
          <div x-show="isLive && selectedSpeaker.id === liveSpeakerId && isViewingLatestPage(selectedSpeaker) && !paused" class="draft-row py-3">
            <span class="text-[10px] font-black shrink-0 w-10 pt-[3px]" style="color: var(--gold);" x-text="formatTime(selectedSpeaker.duration)"></span>
            <textarea
              x-ref="draftInput"
              x-model="draft"
              :disabled="paused"
              @input="onType(); autoGrow()"
              @keydown.enter.prevent="commit()"
              rows="1"
              placeholder="keep typing — your notes shape themselves"
              class="draft-textarea"
              autofocus
            ></textarea>
          </div>

          <p x-show="selectedSpeaker.id && (!isLive || selectedSpeaker.id !== liveSpeakerId) && selectedSpeaker.logs.length"
             class="label pt-4">— archived, view only —</p>
        </div>
      </div>
    </main>

    <!-- ===================== RIGHT — AI STICKY WALL ===================== -->
    <aside class="w-[300px] shrink-0 h-full dashed-v flex flex-col" style="background: var(--bg-light);">
      <div class="px-6 pt-7 pb-4 flex items-center justify-between">
        <div>
          <p class="text-[12px] font-black uppercase" style="letter-spacing:-0.3px;">Ventiq Assist</p>
          <p class="label mt-1" x-text="aiStateLabel()"></p>
        </div>
        <div class="flex items-end gap-[2px] h-4">
          <template x-for="i in 5" :key="i">
            <div class="wave-bar"
                 :style="`animation: wave ${aiState()==='thinking' ? '0.6s' : aiState()==='following' ? '1s' : '1.6s'} ease-in-out infinite; animation-delay:${i*0.09}s; opacity:${aiState()==='thinking' ? 1 : aiState()==='following' ? 0.65 : 0.25}`"></div>
          </template>
        </div>
      </div>

      <!-- this side keeps its own scroll and NEVER flips — it just keeps accumulating -->
      <div class="flex-1 overflow-y-auto px-5 pb-6 pt-2 space-y-6">
        <template x-if="!selectedSpeaker.insights || selectedSpeaker.insights.length === 0">
          <p class="text-[12px] italic px-1" style="color: var(--muted);">Sticky notes will collect here as things come up.</p>
        </template>

        <template x-for="(insight, idx) in (selectedSpeaker.insights || [])" :key="insight.id">
          <div class="sticky sticker-in"
               :style="`background:${categoryTint(insight.type)}; --r:${idx % 3 === 0 ? '-2deg' : idx % 3 === 1 ? '1.5deg' : '-0.8deg'};`">
            <div class="tape"></div>
            <template x-if="insight.pending">
              <div class="flex gap-1 py-1 justify-center">
                <span class="w-1.5 h-1.5 rounded-full bounce-dot" style="background: rgba(15,23,42,0.25); animation-delay:0s"></span>
                <span class="w-1.5 h-1.5 rounded-full bounce-dot" style="background: rgba(15,23,42,0.25); animation-delay:.15s"></span>
                <span class="w-1.5 h-1.5 rounded-full bounce-dot" style="background: rgba(15,23,42,0.25); animation-delay:.3s"></span>
              </div>
            </template>
            <template x-if="!insight.pending">
              <div>
                <span class="badge-pill" :style="`background:${categoryColor(insight.type)}; font-size:8px; padding:3px 8px;`" x-text="insight.type"></span>
                <p class="text-[13px] font-semibold leading-snug mt-2" style="color: var(--ink);" x-text="insight.text"></p>
              </div>
            </template>
          </div>
        </template>
      </div>
    </aside>
  </div>
</div>

<script>
@php
    $initialSegmentsData = $segments->map(function ($s) {
        return [
            'id'       => $s->id,
            'name'     => $s->presenter_name,
            'role'    => $s->role,
            'is_presenting' => $s->is_presenting,
            'topic'    => $s->title ?? '',
            'status'   => $s->status,
            'duration' => $s->duration_seconds ?? 0,
            'logs'     => collect($s->raw_log ?? [])->map(function ($l, $i) {
                return array_merge($l, ['id' => $i]);
            })->values(),
        ];
    });
@endphp
@php
    $roleOptionsData = collect(\App\Support\SessionType::roles($session->type))
        ->map(function ($r, $k) {
            return ['value' => $k, 'label' => $r['label'], 'presenting' => $r['presenting']];
        })
        ->values();
@endphp
function workspace(){
  const sessionId = {{ $session->id }};
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const palette = ['#D4AF37','#10b981','#0f172a','#64748b','#94a3b8'];

  // Real segments from the database — no more hardcoded names.
  const initialSegments = @json($initialSegmentsData);

  const tints = { theme:'#FBF3D9', decision:'#DFF5EC', action:'#E7EAF0', question:'#EEF1F4' };

  async function postJSON(url, body = {}) {
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(body)
      });
      return await res.json();
    } catch (e) {
      console.error('Request failed', url, e);
      return null;
    }
  }

  return {
    isLive: false, thinking: false, draft: '',
    globalClock: initialSegments.reduce((sum, s) => sum + (s.duration || 0), 0),
    liveSpeakerId: null, completedCount: initialSegments.filter(s => s.status === 'completed').length,
    tickHandle: null, typingTimeout: null,
    participantCount: {{ $participantCount }},
    expectedParticipants: @json($session->meta['expected_participants'] ?? null),
    linkCopied: false,

    // ---- pause state ----
    // paused / pauseReason are shared by both the manual Pause button and the
    // automatic tab-visibility handler below. 'manual' means the user hit
    // Pause on purpose (e.g. lunch) and only the user can resume it. 'auto'
    // means the tab went into the background and it's safe to auto-resume
    // the moment the tab is visible again.
    paused: false,
    pauseReason: null, // 'manual' | 'auto' | null

    // ---- pagination state (visual only — underlying logs array is untouched) ----
    pageBreakIndex: 0,
    pageNumber: 1,
    flipping: false,

    showAddPresenter: false,
    newPresenterName: '',
    newPresenterRole: '',
    newPresenterPresenting: true,
    roleOptions: @json($roleOptionsData),
    speakers: initialSegments.map((s, i) => ({
      id: s.id, name: s.name, topic: s.topic, role: s.role, isPresenting: !!s.is_presenting,
      initials: s.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
      color: palette[i % palette.length],
      status: s.status, duration: s.duration, logs: s.logs, insights: [],
      pageBreaks: [0], viewPageIndex: 0
    })),
    selectedSpeaker: {},
    pageFillRatio: 0,

    // only the lines belonging to whichever page is currently being VIEWED are rendered —
    // this is what makes "go back a page" possible without touching the underlying logs.
    get visibleLogs(){
      const sp = this.selectedSpeaker;
      if (!sp.logs || !sp.pageBreaks) return [];
      const start = sp.pageBreaks[sp.viewPageIndex] ?? 0;
      const end = sp.pageBreaks[sp.viewPageIndex + 1] ?? sp.logs.length;
      return sp.logs.slice(start, end);
    },

    isViewingLatestPage(speaker){
      if (!speaker || !speaker.pageBreaks) return true;
      return speaker.viewPageIndex === speaker.pageBreaks.length - 1;
    },
    // true only when the presenter currently live is the last one in the roster —
    // that's the one moment "finish this segment" actually means "end the session"
    get isLastSpeaker(){
      const idx = this.speakers.findIndex(s => s.id === this.liveSpeakerId);
      return idx === -1 || idx === this.speakers.length - 1;
    },
    goToPreviousPage(){
      if (this.selectedSpeaker.viewPageIndex > 0) this.selectedSpeaker.viewPageIndex--;
    },
    goToNextPage(){
      if (this.selectedSpeaker.viewPageIndex < this.selectedSpeaker.pageBreaks.length - 1) this.selectedSpeaker.viewPageIndex++;
    },
    returnToCurrentPage(){
      this.selectedSpeaker.viewPageIndex = this.selectedSpeaker.pageBreaks.length - 1;
      this.$nextTick(() => this.$refs.draftInput?.focus());
    },

    init() {
      const active = this.speakers.find(s => s.status === 'active');
      if (active) {
        // Resuming a session that was already underway.
        this.isLive = true;
        this.liveSpeakerId = active.id;
        this.selectedSpeaker = active;
        this.tickHandle = setInterval(() => {
          this.globalClock++;
          active.duration++;
        }, 1000);
      } else {
        this.selectedSpeaker = this.speakers[0] || {};
      }

      if ({{ $session->event_id ? 'true' : 'false' }}) {
        setInterval(async () => {
          const res = await fetch(`{{ route('sessions.participants.count', $session) }}`);
          if (res.ok) {
            const data = await res.json();
            this.participantCount = data.count;
          }
        }, 8000);
      }

      // ---- Page Visibility auto-pause ----
      // The browser tells us directly when this tab stops being the one the
      // user is looking at — no polling, no inactivity timers, and it still
      // fires even if they switch away rather than closing anything.
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          if (this.isLive && !this.paused) this.pauseLive('auto');
        } else {
          // only auto-resume what WE auto-paused — never override a
          // deliberate manual pause just because the tab became visible
          if (this.isLive && this.paused && this.pauseReason === 'auto') this.resumeLive();
        }
      });
    },

    copyCheckinLink(){
      navigator.clipboard.writeText('{{ $session->public_token ? route('public.session-checkin.form', $session->public_token) : '' }}');
      this.linkCopied = true;
      setTimeout(() => this.linkCopied = false, 2000);
    },

    groupLabel(g){ return { active:'Presenting Now', upcoming:'Up Next', completed:'Wrapped' }[g]; },

    // grows the textarea to fit its wrapped content, capped by CSS max-height if you add one
    autoGrow(){
      const el = this.$refs.draftInput;
      if (el) {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
      }
      this.updateFillRatio();
    },

    // how close the visible page is to overflowing — drives the quiet
    // "page filling up" nudge before the flip actually happens
    updateFillRatio(){
      const notes = this.$refs.notesArea;
      if (!notes) return;
      const contentEl = notes.firstElementChild;
      if (!contentEl) return;
      this.pageFillRatio = contentEl.scrollHeight / notes.clientHeight;
    },

    async startSession(){
      await postJSON(`/sessions/${sessionId}/start`);

      this.isLive = true;

      const first = this.speakers[0];
      if (first) {
        first.status = 'active';
        this.liveSpeakerId = first.id;
        this.selectedSpeaker = first;
      }
      // clock runs regardless of whether anyone's on stage yet —
      // total elapsed session time should stay honest either way
      this.tickHandle = setInterval(() => {
        this.globalClock++;
        const live = this.speakers.find(s => s.id === this.liveSpeakerId);
        if (live) live.duration++;
      }, 1000);

      this.$nextTick(() => this.autoGrow());
    },
    focusSpeaker(s){
      this.selectedSpeaker = s;
      // no pagination reset here on purpose — s.pageBreaks / s.viewPageIndex
      // belong to that speaker and persist, so coming back to someone you
      // already captured shows the page you left off on, not page 1.
      this.$nextTick(() => this.autoGrow());
    },

    async addPresenter(){
      const name = this.newPresenterName.trim();
      if (!name) return;

      const result = await postJSON(`/sessions/${sessionId}/segments`, { name, role: this.newPresenterRole || null, presenting: this.newPresenterPresenting });
      if (!result || result.status !== 'ok') return;

      const seg = result.segment;
      const newSpeaker = {
        id: seg.id, name: seg.name, topic: '', role: seg.role, isPresenting: !!seg.is_presenting,
        initials: seg.name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase(),
        color: palette[this.speakers.length % palette.length],
        status: seg.status, duration: 0, logs: [], insights: [],
        pageBreaks: [0], viewPageIndex: 0
      };
      this.speakers.push(newSpeaker);
      this.newPresenterName = '';
      this.newPresenterRole = '';
      this.newPresenterPresenting = true;
      this.showAddPresenter = false;

      if (seg.status === 'active') {
        this.liveSpeakerId = newSpeaker.id;
        this.selectedSpeaker = newSpeaker;
        if (!this.tickHandle) {
          this.tickHandle = setInterval(() => { this.globalClock++; const live = this.speakers.find(s => s.id === this.liveSpeakerId); if (live) live.duration++; }, 1000);
        }
        this.$nextTick(() => this.autoGrow());
      }
    },

    // ---- pause / resume ----
    // Both the manual button and the visibility handler funnel through these
    // two methods, so there's exactly one place the clock actually stops
    // and starts, and one place the server is told about it.
    async pauseLive(reason){
      if (!this.isLive || this.paused) return;
      const live = this.speakers.find(s => s.id === this.liveSpeakerId);
      if (!live) return;

      clearInterval(this.tickHandle);
      this.paused = true;
      this.pauseReason = reason;

      await postJSON(`/sessions/${sessionId}/segments/${live.id}/pause`);
    },

    async resumeLive(){
      if (!this.isLive || !this.paused) return;
      const live = this.speakers.find(s => s.id === this.liveSpeakerId);
      if (!live) return;

      const result = await postJSON(`/sessions/${sessionId}/segments/${live.id}/resume`);

      this.paused = false;
      this.pauseReason = null;

      // trust the server's paused-seconds accounting for exact duration,
      // but don't block on it — the tick keeps counting locally either way
      if (result?.duration_seconds != null) live.duration = result.duration_seconds;

      this.tickHandle = setInterval(() => {
        this.globalClock++;
        live.duration++;
      }, 1000);

      this.$nextTick(() => this.$refs.draftInput?.focus());
    },

    togglePause(){
      if (this.paused && this.pauseReason === 'manual') {
        this.resumeLive();
      } else if (!this.paused) {
        this.pauseLive('manual');
      }
      // if paused for 'auto' reasons the button is inert — the tab
      // becoming visible again is what resumes it, not a click
    },

    onType(){
      clearTimeout(this.typingTimeout);
      this.typingTimeout = setTimeout(() => {}, 800);
    },

    aiState(){
      if (this.thinking) return 'thinking';
      if (this.isLive && this.draft.trim().length > 0) return 'following';
      if (this.isLive) return 'listening';
      return 'idle';
    },
    aiStateLabel(){
      return { thinking:'Reading', following:'Following along', listening:'Listening', idle:'Idle' }[this.aiState()];
    },

    commit(){
      if (!this.draft.trim()) return;
      const live = this.speakers.find(s => s.id === this.liveSpeakerId);
      const text = this.draft.trim();
      live.logs.push({ id: Date.now(), time: this.formatTime(live.duration), text });
      this.draft = '';

      // collapse the textarea back to one row, then re-measure for overflow
      this.$nextTick(() => {
        this.autoGrow();
        this.checkPageOverflow();
      });

      // Persisted in the background — typing never waits on this.
      postJSON(`/sessions/${sessionId}/segments/${live.id}/log`, { text });

      this.surface(live, text);
    },

    // Measures the notebook page; if the committed lines + draft row no
    // longer fit, flips to a fresh visual page. Nothing is deleted —
    // a new breakpoint is pushed onto the speaker's own pageBreaks array,
    // so older lines aren't rendered on the current page but are still
    // in `logs` (for the report) and still reachable via "go back".
    checkPageOverflow(){
      const el = this.$refs.notesArea;
      if (!el) return;
      const contentEl = el.firstElementChild; // the max-w-2xl wrapper
      if (!contentEl) return;

      const live = this.speakers.find(s => s.id === this.liveSpeakerId);
      if (!live) return;

      if (contentEl.scrollHeight > el.clientHeight + 2) {
        this.flipping = true;
        setTimeout(() => {
          // break just before the last committed line, so the new page
          // always opens with something already on it
          const total = live.logs.length;
          const lastBreak = live.pageBreaks[live.pageBreaks.length - 1];
          const newBreak = Math.max(lastBreak, total - 1);
          if (newBreak > lastBreak) live.pageBreaks.push(newBreak);
          live.viewPageIndex = live.pageBreaks.length - 1; // auto-follow onto the new page while typing
          this.flipping = false;
          this.pageFillRatio = 0;
          this.$nextTick(() => this.$refs.draftInput?.focus());
        }, 240); // must match .page-flip-out animation duration
      }
    },

    // Real AI call — no more random-chance simulation. A "none" result
    // is a normal outcome, not an error: most lines shouldn't surface.
    async surface(speaker, line){
      this.thinking = true;
      const pendingId = Date.now();
      speaker.insights.push({ id: pendingId, pending: true, type: 'theme', text: '' });

      const result = await postJSON(`/sessions/${sessionId}/segments/${speaker.id}/tag`, { line });

      if (!result || result.category === 'none' || !result.text) {
        speaker.insights = speaker.insights.filter(i => i.id !== pendingId);
      } else {
        const item = speaker.insights.find(i => i.id === pendingId);
        item.pending = false;
        item.type = result.category;
        item.text = result.text;
      }
      this.thinking = false;
    },

    async finishSegment(){
      const idx = this.speakers.findIndex(s => s.id === this.liveSpeakerId);
      const current = this.speakers[idx];

      const result = await postJSON(`/sessions/${sessionId}/segments/${current.id}/finish`);

      current.status = 'completed';
      this.completedCount++;

      const next = this.speakers[idx+1];
      if (next && result?.next_segment_id === next.id) {
        next.status = 'active';
        this.liveSpeakerId = next.id;
        this.selectedSpeaker = next;
      } else {
        clearInterval(this.tickHandle);
        this.isLive = false;
        window.location.href = `/sessions/${sessionId}/report`;
      }
    },

    categoryColor(type){ return { theme:'#D4AF37', decision:'#10b981', action:'#0f172a', question:'#64748b' }[type] || '#94a3b8'; },
    categoryTint(type){ return tints[type] || '#F1F5F9'; },
    formatTime(sec){
      const m = Math.floor(sec/60).toString().padStart(2,'0');
      const s = (sec%60).toString().padStart(2,'0');
      return `${m}:${s}`;
    }
  }
}
</script>
</body>
</html>