{{-- The loading screen: a short hello on a cold visit, the Lumela
     greeting on a browser's very first one, and almost nothing on later
     page loads in the same tab. Used by the React root (inertia.blade.php). --}}
<style>
    #page-loader { transition: opacity .7s cubic-bezier(.4,0,.2,1), visibility .7s cubic-bezier(.4,0,.2,1); }
    #page-loader.done { opacity: 0; visibility: hidden; pointer-events: none; }
</style>
<div id="page-loader"
     class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-[#1D4069]">

    <div class="flex flex-col items-center gap-4">

        {{-- First visit only: the Lumela greeting (partials/lumela). --}}
        @include('partials.lumela')

        {{-- Pure CSS: no animation player to download first. --}}
        <span class="loader-dot relative flex h-16 w-16 items-center justify-center">
            <span class="absolute inset-0 rounded-full bg-[#F07F22]/30 animate-ping"></span>
            <span class="relative h-10 w-10 rounded-full bg-[#F07F22]"></span>
        </span>

        <div class="flex flex-col items-center gap-1">
            <span class="text-2xl font-black tracking-tighter uppercase text-white">
                VENTI<span class="text-[#F07F22]">Q</span>
            </span>
            <span class="text-[9px] font-bold tracking-[0.3em] text-white/40 uppercase">
                Intelligence
            </span>
        </div>
    </div>

    <p class="absolute bottom-10 text-[9px] font-bold tracking-[0.3em] text-white/20 uppercase">
        Simply Connected
    </p>
</div>
{{-- Decided before the loader paints: greet a browser's very first visit. --}}
<script>
    (function () {
        try {
            var warm = sessionStorage.getItem('ventiq_booted') === '1';
            if (!warm && !localStorage.getItem('ventiq_greeted')) {
                document.getElementById('page-loader').classList.add('greet');
                localStorage.setItem('ventiq_greeted', '1');
            }
        } catch (e) {}
    })();
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const loader = document.getElementById('page-loader');
        let dismissed = false;

        // Has this browser tab already seen VENTIQ boot up once this session?
        let isWarmNavigation = false;
        try { isWarmNavigation = sessionStorage.getItem('ventiq_booted') === '1'; } catch (e) {}

        function dismissLoader(fast = false) {
            if (dismissed) return;
            dismissed = true;
            loader.classList.add('done');
            if (fast) loader.style.transitionDuration = '150ms';
            setTimeout(() => { document.body.style.overflow = ''; }, fast ? 150 : 300);
        }

        if (loader.classList.contains('greet')) {
            // A browser's first visit: let the Lumela greeting play (about
            // 1.8s), or skip it with a tap. Once only, ever.
            try { sessionStorage.setItem('ventiq_booted', '1'); } catch (e) {}
            loader.addEventListener('click', () => dismissLoader(true), { once: true });
            setTimeout(() => dismissLoader(false), 1900);
        } else if (isWarmNavigation) {
            // Internal navigation — user already knows the app is fast.
            // Skip the theatrics, dismiss almost immediately.
            dismissLoader(true);
        } else {
            // Cold entry: a short hello, then the page. Never wait for images
            // (window "load" waits for every poster on the page).
            try { sessionStorage.setItem('ventiq_booted', '1'); } catch (e) {}
            requestAnimationFrame(() => setTimeout(() => dismissLoader(false), 250));

            // Safety net
            setTimeout(() => dismissLoader(false), 900);
        }
    });
</script>
