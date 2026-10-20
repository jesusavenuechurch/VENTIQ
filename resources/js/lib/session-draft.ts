/**
 * A session being scheduled, kept for the browser tab's visit so leaving
 * the form (to check Reports, say) loses nothing: the form comes back as
 * it was, and the desk offers to carry on.
 */
export const DRAFT_KEY = 'sessions:create-draft';

export interface SessionDraft<T = Record<string, unknown>> {
    data: T & { title?: string; programme_id?: number | null };
    step: 1 | 2;
    person: { name: string; role: string; presenting: boolean };
}

export function readDraft<T>(): SessionDraft<T> | null {
    try {
        return JSON.parse(window.sessionStorage.getItem(DRAFT_KEY) ?? 'null');
    } catch {
        return null;
    }
}

export function saveDraft(draft: SessionDraft): void {
    try {
        window.sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    } catch {
        // Private mode: no draft.
    }
}

export function clearDraft(): void {
    try {
        window.sessionStorage.removeItem(DRAFT_KEY);
    } catch {
        // Nothing stored.
    }
}
