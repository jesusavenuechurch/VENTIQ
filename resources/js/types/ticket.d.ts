/** A ticket as the attendee pages see it (App\Support\TicketPage::ticket). */
export interface TicketData {
    number: string;
    code: string;
    holderName: string | null;
    admissions: number;
    voucherCode: string | null;
    status: string;
    paymentStatus: string;
    amount: number;
    amountPaid: number;
    owed: number;
    paymentDueAt: string | null;
    tier: { name: string | null; isVip: boolean };
    link: string;
    event: {
        name: string;
        date: string | null;
        venue: string | null;
        location: string | null;
        organization: string | null;
    };
}
