/** An event on the public pages (App\Support\EventPage::event). */
export interface EventData {
    name: string;
    slug: string;
    status: string;
    type: string | null;
    description: string;
    date: string | null;
    shortDate: string | null;
    time: string | null;
    venue: string | null;
    banner: string | null;
    allowInstallments: boolean;
    depositPercent: number;
    organization: { name: string; slug: string; contactEmail: string | null };
}

/** A ticket type as sold (App\Support\EventPage::tier). */
export interface TierData {
    id: number;
    name: string;
    description: string | null;
    price: number;
    priceLabel: string;
    admits: number;
    soldOut: boolean;
    /** How many are left, once ten or fewer. */
    fewLeft: number | null;
    /** "M50": the smallest deposit, when deposits are allowed. */
    depositFrom: string | null;
}
