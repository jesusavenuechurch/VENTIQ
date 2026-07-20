<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ventiq — Live Session</title>
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

  /* spiral binding hint along the notes' left edge */
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

  /* sticky note */
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
        <p class="text-[11px] font-bold uppercase mt-0.5" style="color: var(--muted);">Science Fair Finals</p>
      </div>

      <div class="flex-1 overflow-y-auto px-3 pb-4 space-y-5">
        <template x-for="group in ['active','upcoming','completed']" :key="group">
          <div x-show="speakers.some(s => s.status === group)">
            <p class="label px-2 mb-2" x-text="groupLabel(group)"></p>
            <template x-for="speaker in speakers.filter(s => s.status === group)" :key="speaker.id">
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
          </div>
        </template>
      </div>

      <div class="px-5 py-4 flex items-center justify-between" style="border-top: 2px dashed var(--line);">
        <span class="label" x-text="`${completedCount}/${speakers.length} done`"></span>
        <span class="text-[10px] font-black" style="color: var(--ink);" x-text="formatTime(globalClock)"></span>
      </div>
    </aside>

    <!-- ===================== CENTER — NOTES ===================== -->
    <main class="flex-1 h-full flex flex-col relative overflow-hidden">
      <div class="spiral-strip"></div>
      <div class="watermark">Ventiq</div>

      <header class="relative z-10 pl-14 lg:pl-16 pr-10 lg:pr-14 pt-9 pb-5 flex items-start justify-between">
        <div class="flex-1 min-w-0">
          <span class="badge-pill mb-3" :style="`background:${isLive ? 'var(--gold)' : 'var(--muted-light)'}`" x-text="isLive ? 'Capturing' : 'Ready'"></span>
          <h1 class="value text-[28px] leading-none mt-3" style="letter-spacing:-1px;" x-text="selectedSpeaker.name || 'Select a presenter'"></h1>
          <input x-show="selectedSpeaker.id" x-model="selectedSpeaker.topic"
                 class="topic-input mt-1.5" placeholder="Untitled — click to name this presentation">
        </div>
        <button x-show="isLive && selectedSpeaker.id === liveSpeakerId"
                @click="finishSegment()"
                class="badge-pill cursor-pointer transition hover:opacity-80 shrink-0 mt-1"
                style="background: var(--ink);">
          Finish Segment →
        </button>
      </header>

      <div class="relative z-10 flex-1 overflow-y-auto pl-14 lg:pl-16 pr-10 lg:pr-14 pb-6">
        <div class="max-w-2xl">

          <template x-if="!isLive && !speakers.some(s=>s.logs.length)">
            <div class="pt-16">
              <p class="text-[16px] italic" style="color: var(--muted);">The page is empty. Start the session and write as they speak.</p>
              <button @click="startSession()" class="badge-pill mt-5 cursor-pointer" style="background: var(--emerald);">
                Begin Capturing
              </button>
            </div>
          </template>

          <template x-for="log in selectedSpeaker.logs" :key="log.id">
            <div class="py-3 flex gap-4 items-baseline" style="border-bottom: 1px dashed var(--line);">
              <span class="text-[10px] font-black shrink-0 w-10" style="color: var(--muted-light);" x-text="log.time"></span>
              <p class="text-[15px] leading-relaxed" style="color: var(--ink);" x-text="log.text"></p>
            </div>
          </template>

          <div x-show="isLive && selectedSpeaker.id === liveSpeakerId" class="py-3 flex gap-4 items-baseline">
            <span class="text-[10px] font-black shrink-0 w-10" style="color: var(--gold);" x-text="formatTime(selectedSpeaker.duration)"></span>
            <input type="text" x-model="draft" @input="onType()" @keydown.enter="commit()"
                   placeholder="keep typing — your notes shape themselves"
                   class="flex-1 bg-transparent outline-none text-[15px] italic"
                   style="color: var(--ink); caret-color: var(--gold);" autofocus>
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
function workspace(){
  const palette = ['#D4AF37','#10b981','#0f172a','#64748b','#94a3b8'];
  const names = ['Thabo Motaung','Palesa Khumalo','Lindiwe Ntsane','Karabo Sethunya','Amahle Tau'];
  const insightPool = {
    theme:   ['renewable energy access','water scarcity solutions','local data collection methods','youth-led innovation'],
    decision:['panel shortlists this project','moved to final round','budget for prototype approved'],
    action:  ['send feedback form to presenter','schedule follow-up demo','connect presenter with a mentor'],
    question:['how does this scale beyond the pilot?','what is the cost per unit?']
  };
  const tints = { theme:'#FBF3D9', decision:'#DFF5EC', action:'#E7EAF0', question:'#EEF1F4' };

  return {
    isLive: false, thinking: false, draft: '',
    globalClock: 0, liveSpeakerId: null, completedCount: 0, tickHandle: null, typingTimeout: null,
    speakers: names.map((n,i) => ({
      id: i+1, name: n, topic: '',
      initials: n.split(' ').map(w=>w[0]).join(''),
      color: palette[i % palette.length],
      status: 'upcoming', duration: 0, logs: [], insights: []
    })),
    selectedSpeaker: {},

    groupLabel(g){ return { active:'Presenting Now', upcoming:'Up Next', completed:'Wrapped' }[g]; },

    startSession(){
      this.isLive = true;
      this.speakers[0].status = 'active';
      this.liveSpeakerId = this.speakers[0].id;
      this.selectedSpeaker = this.speakers[0];
      this.tickHandle = setInterval(() => {
        this.globalClock++;
        const live = this.speakers.find(s => s.id === this.liveSpeakerId);
        if (live) live.duration++;
      }, 1000);
    },
    focusSpeaker(s){ this.selectedSpeaker = s; },

    // called on every keystroke — this is the "AI is following along" pulse, before Enter
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
      live.logs.push({ id: Date.now(), time: this.formatTime(live.duration), text: this.draft.trim() });
      this.draft = '';
      this.surface(live);
    },

    surface(speaker){
      if (Math.random() < 0.35) return;
      this.thinking = true;
      const pendingId = Date.now();
      speaker.insights.push({ id: pendingId, pending: true, type: 'theme', text: '' });
      setTimeout(() => {
        const types = Object.keys(insightPool);
        const type = types[Math.floor(Math.random()*types.length)];
        const pool = insightPool[type];
        const text = pool[Math.floor(Math.random()*pool.length)];
        const item = speaker.insights.find(i => i.id === pendingId);
        item.pending = false; item.type = type; item.text = text;
        this.thinking = false;
      }, 900 + Math.random()*500);
    },

    finishSegment(){
      const idx = this.speakers.findIndex(s => s.id === this.liveSpeakerId);
      this.speakers[idx].status = 'completed';
      this.completedCount++;
      const next = this.speakers[idx+1];
      if (next) {
        next.status = 'active';
        this.liveSpeakerId = next.id;
        this.selectedSpeaker = next;
      } else {
        clearInterval(this.tickHandle);
        this.isLive = false;
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