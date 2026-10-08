# Khoebo: VENTIQ's accounting

Khoebo is the accounting system VENTIQ bills organizers from. Today a super
admin downloads an organization's uninvoiced fees (Money page), raises the
invoice by hand and records its reference in VENTIQ. Khoebo's API lets
VENTIQ do that itself.

## Who is who

| Khoebo | VENTIQ |
|---|---|
| Customer | The organizer (organization). Attendees are never Khoebo customers: they pay the organizer, not VENTIQ. |
| Product | A fee VENTIQ charges the organizer (two products, below). Tickets are the organizer's, not VENTIQ's. |
| Order | One per event: the *possible* fees, from the ticket numbers the organizer must now enter. |
| Invoice | What the organizer actually owes after the event: active tickets (comps included) and real sales. |
| Payment | The organizer paying the invoice, or the fee kept from an online payout ("deducted from payout"). |

## Products (listed in VENTIQ, made in Khoebo by a command)

The list is `services.khoebo.products` in config/services.php.
`php artisan khoebo:products` makes in Khoebo any that it doesn't have yet,
and keeps their Khoebo ids in the `khoebo_products` table. Run it once on
each Khoebo (dev, then live). To sell something new (e.g. Sessions), add it
to the list and run the command again.

VENTIQ isn't VAT registered, so products use the Exempt tax
(`KHOEBO_TAX_IDS`, 4 on dev).

| Product | SKU | Price | On an order line |
|---|---|---|---|
| VENTIQ per-person fee | VQ-PERSON | 7.50 | quantity = people |
| VENTIQ ticket sales fee (4.9%) | VQ-SALES | set per event | quantity 1, price = 4.9% of ticket sales |

Type `service`.

## Example

100 Standard at M200 and 20 VIP at M500, published:
order = 120 × M7.50 (M900) + 4.9% × M30,000 (M1,470) = **M2,370**.
After the event, 90 people and M21,000 sold:
invoice = 90 × M7.50 (M675) + 4.9% × M21,000 (M1,029) = **M1,704**.

A free event has only the per-person line, from "How many people are you
expecting?".

## What we know about the API (dev)

- Base `https://dev.khoebo.co.ls/api/v1`, `Authorization: Bearer <token>`,
  JSON, limit 120 requests a minute.
- Creates take an `Idempotency-Key` header: the same key returns the record
  made the first time. VENTIQ's keys say what they're for
  (`ventiq-customer-org-12`).
- Records take an `external_reference`: VENTIQ's id (`ventiq-org-12`).
- `POST /customers`: name, is_company, email, phone, tax_number,
  external_reference, address {street, city, country_code}. Returns `data.id`.
- `POST /products`: name, sku, type, sale_price, tax_ids, external_reference.
  Products default to currency LSL and `invoicing_policy:
  ordered_quantities`.

- `POST /orders`: customer_id, payment_term_id, external_reference, notes,
  lines [{product_id, quantity, unit_price, discount_rate, description}].
  **A line's unit_price overrides the product's price** (the 4.9% line
  relies on it). Returns `data.id`, `data.reference` (QT-00001), status
  `draft`, invoice_status `nothing_to_invoice`, totals.
- `POST /orders/{id}/confirm` and `/send` are refused on dev:
  `order_not_confirmable` / `order_not_sendable`, "This company confirms
  sales orders through an approval workflow, which needs a signed-in user."
  That's a setting on VENTIQ's company in Khoebo (approvals), not a broken
  call. Ask Khoebo to switch the approval off for VENTIQ, or to let API
  orders skip it. Until then orders stay drafts and are confirmed in
  Khoebo's screens.

## Built so far

- `KhoeboClient` (token, idempotency keys, retries on a lost connection,
  readable errors including Cloudflare blocks).
- Organizations get a `khoebo_customer_id`; `KhoeboCustomers::ensure()`
  makes an organization a customer once.
- `php artisan khoebo:customer {organization id}` to try it, and to check
  this server can reach Khoebo.
- `php artisan khoebo:products` makes VENTIQ's products in Khoebo.
- `php artisan khoebo:order {event id}` makes the event's draft order from
  its ticket numbers (sponsored events: none; free events: the per-person
  line only). The order's id and reference are kept on the event.

## Still to find out, then build

1. **Confirming orders** once Khoebo lifts the approval for API calls;
   updating an order when the organizer raises their numbers.
2. **Invoices:** invoice an order; whether the "delivered quantities" policy
   lets the invoice bill actual numbers instead of the order's.
3. **Payments:** record a received payment against an invoice; payment
   methods (M-Pesa, EcoCash, bank, deducted from payout).
4. **GET** customers / invoices, and invoice status (paid, part-paid).

To revisit: whether the order is made at publish (and updated when numbers
go up) or only at invoicing time.

## Before go-live

- Khoebo's Cloudflare blocks server-to-server calls from unknown hosts: ask
  Khoebo to allow VENTIQ's production server IP for `/api/*`.
- Get a new token for production (the dev token was shared in chat).
