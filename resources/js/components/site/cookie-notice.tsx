import { useEffect, useState } from 'react';

const KEY = 'ventiq_cookie_notice_dismissed';

/**
 * A courtesy notice, not a consent gate: VENTIQ only sets its own session
 * and CSRF cookies (strictly necessary, no trackers). Dismissed once, it
 * stays dismissed on this browser.
 */
export function CookieNotice() {
    const [show, setShow] = useState(false);

    useEffect(() => {
        try {
            setShow(!localStorage.getItem(KEY));
        } catch {
            // Storage blocked (private mode): don't nag.
        }
    }, []);

    if (!show) return null;

    return (
        <div className="fixed inset-x-0 bottom-0 z-[70] p-4">
            <div className="mx-auto flex max-w-3xl flex-col items-center gap-4 rounded-2xl bg-brand px-6 py-4 text-white shadow-2xl sm:flex-row">
                <p className="flex-1 text-center text-xs font-medium sm:text-left sm:text-sm">We use essential cookies to keep your session secure. By continuing to use this site, you agree to this.</p>
                <button
                    type="button"
                    onClick={() => {
                        try {
                            localStorage.setItem(KEY, '1');
                        } catch {
                            // Nothing to remember it in.
                        }
                        setShow(false);
                    }}
                    className="shrink-0 rounded-full bg-action px-5 py-2 text-xs font-black uppercase tracking-widest text-white transition-all hover:bg-white hover:text-brand"
                >
                    Got it
                </button>
            </div>
        </div>
    );
}
