/**
 * The details someone registered with, kept in this browser only (never
 * sent anywhere), so the next registration on the same phone fills itself
 * in. "Forget me on this phone" clears it, for shared phones.
 */
export type Delivery = 'whatsapp' | 'email' | 'both';

export type RememberedPerson = { name: string; position?: string; institution?: string; district?: string };

export type RememberedAttendee = {
    phone: string;
    email: string;
    delivery: Delivery;
    people: RememberedPerson[];
    /** Organizers (by slug) they said yes to hearing from: the box is ticked for them next time. */
    updatesFor: string[];
};

const KEY = 'ventiq.attendee';
const MAX_PEOPLE = 6;

export function recall(): RememberedAttendee | null {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) ?? 'null');
        if (!saved || typeof saved.phone !== 'string' || !Array.isArray(saved.people)) return null;
        return {
            phone: saved.phone,
            email: typeof saved.email === 'string' ? saved.email : '',
            delivery: ['whatsapp', 'email', 'both'].includes(saved.delivery) ? saved.delivery : 'whatsapp',
            people: saved.people.filter((p: RememberedPerson) => p && typeof p.name === 'string' && p.name.trim() !== '').slice(0, MAX_PEOPLE),
            updatesFor: Array.isArray(saved.updatesFor) ? saved.updatesFor.filter((s: unknown) => typeof s === 'string') : [],
        };
    } catch {
        return null;
    }
}

/** Saved after registering: this person first, then the others used before. */
export function remember(details: Omit<RememberedAttendee, 'people' | 'updatesFor'>, person: RememberedPerson, updates: { organizer: string; yes: boolean }): void {
    try {
        const before = recall();
        // A different number is a different person's phone details: start again.
        const same = before && before.phone === details.phone;
        const others = same ? before.people : [];
        const isPerson = (p: RememberedPerson) => p.name.trim().toLowerCase() === person.name.trim().toLowerCase();
        const updatesFor = (same ? before.updatesFor : []).filter((s) => s !== updates.organizer).concat(updates.yes ? [updates.organizer] : []);
        localStorage.setItem(KEY, JSON.stringify({ ...details, people: [person, ...others.filter((p) => !isPerson(p))].slice(0, MAX_PEOPLE), updatesFor }));
    } catch {
        // Private browsing, storage full or blocked: registering works the same without it.
    }
}

export function forget(): void {
    try {
        localStorage.removeItem(KEY);
    } catch {
        // Nothing to clear.
    }
}
