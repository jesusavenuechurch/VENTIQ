export interface Merchant {
    code: string;
    name: string;
}

export interface Account {
    id: number;
    label: string;
    isCash: boolean;
    accountLabel: string;
    number: string | null;
    instructions: string | null;
}

export interface OnlineOptions {
    methods: string[];
    attemptsLeft: number;
    maxAttempts: number;
    waitSeconds: number;
    inFlight: { id: number; elapsed: number } | null;
    phone: string | null;
}

/** The answer from ticket.pay.online and ticket.pay.status. */
export interface PushState {
    session_id?: number;
    status?: 'pending' | 'completed' | 'failed' | string;
    message?: string | null;
    attempts_left?: number;
    wait_seconds?: number;
}

/** A way to pay VENTIQ by hand, with no PIN prompt (M-Pesa to its number, a bank transfer). */
export interface ManualOption {
    method: 'mpesa' | 'bank';
    label: string;
    lines: { label: string; value: string }[];
}
