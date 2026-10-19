{{-- Simple, dependency-free cookie notice. This app only sets Laravel's own
     session/CSRF cookies (strictly necessary — no analytics/marketing
     trackers), so this is a courtesy notice rather than a consent gate:
     dismissing doesn't block anything, it just remembers not to show again. --}}
<div id="cookie-notice" class="hidden fixed inset-x-0 bottom-0 z-50 p-4">
    <div class="max-w-3xl mx-auto bg-[#1D4069] text-white rounded-2xl shadow-2xl px-6 py-4 flex flex-col sm:flex-row items-center gap-4">
        <p class="text-xs sm:text-sm font-medium flex-1 text-center sm:text-left">
            We use essential cookies to keep your session secure. By continuing to use this site, you agree to this.
        </p>
        <button type="button" id="cookie-notice-accept"
            class="shrink-0 px-5 py-2 bg-[#F07F22] hover:bg-white hover:text-[#1D4069] text-white text-xs font-black uppercase tracking-widest rounded-full transition-all">
            Got it
        </button>
    </div>
</div>
<script>
    (function () {
        var KEY = 'ventiq_cookie_notice_dismissed';
        if (localStorage.getItem(KEY)) return;

        var notice = document.getElementById('cookie-notice');
        notice.classList.remove('hidden');

        document.getElementById('cookie-notice-accept').addEventListener('click', function () {
            localStorage.setItem(KEY, '1');
            notice.classList.add('hidden');
        });
    })();
</script>
