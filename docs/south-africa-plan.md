# VENTIQ in South Africa: plan

**Decision needed:** how to open VENTIQ to an organizer in South Africa without rebuilding payments.
**Recommendation:** treat location as a way to *rank* events, never to *limit* them, and stop assuming every place and phone number is in Lesotho. About one focused piece of work; no change to how money moves.

## Assumptions (from the client)

- Their tickets are **free**, or paid **manually** to the organizer (EFT, SnapScan, cash). VENTIQ already supports both.
- No online payments in SA. PayLesotho stays Lesotho-only.
- VENTIQ's own fee still applies: on free tickets that's 7.50 per person, invoiced to the organizer. The organizer's job is to make sure that invoice is paid to us.
- Attendees can come from **anywhere**: SA, Lesotho, or elsewhere. An SA event must be findable and joinable by anyone.

## What stops SA today

| Where | What it assumes | Effect for an SA organizer or attendee |
|---|---|---|
| Event form | "District" must be one of Lesotho's 10 | An SA event can't say where it is |
| Location detection | Snaps the visitor to the nearest Lesotho district | A visitor in Johannesburg is treated as being in Lesotho |
| District filter and "New in {district}" | Exact match on a district name | SA events never appear under any location |
| Phone fields (register, pay, Find my ticket, WhatsApp) | +266 and 8 digits, in about 38 places | **An SA attendee can't register at all, even for a free ticket** |
| Prices and invoices | "M" | Shows maloti to rand users (same value, 1 loti = 1 rand, wrong symbol) |

## The fix

### 1. Places instead of districts

- One list of **places** (towns and cities), each with a **country**, a **region** (Lesotho district or SA province) and **map coordinates**. Seed it with Lesotho's districts and towns, and SA's main cities and towns (a few hundred is plenty).
- The event form asks for a **town or city** (type to search the list), not a district. Country and coordinates come with it. The venue stays free text.
- Every event stores its country, region, town and coordinates.

### 2. Same location flow as today, beyond Lesotho

**Today:** the browser asks for the visitor's location, and VENTIQ snaps them to the nearest of Lesotho's 10 districts (for example "Maseru"). The home page shows that district's events first and falls back to the whole country when the district has none. Search lists that district first, without hiding others, and the picker lists the 10 districts.

**With this plan, the flow stays the same; only the places widen:**

- The browser still asks for location. VENTIQ snaps the visitor to the nearest **place in the list**, shown with its country: **"Maseru, Lesotho"**, **"Ladybrand, South Africa"**, **"Johannesburg, South Africa"**. Nobody in SA is put in a Lesotho district any more.
- The home page shows events **near that place** first (within about 100 km, instead of "same district"). It falls back to that place's **country**, then **everywhere**. A Maseru visitor sees Ladybrand and Ficksburg events as nearby; a Johannesburg visitor sees Gauteng events first.
- Search keeps ranking nearby events first and still lists the rest, across both countries.
- The picker becomes a type-to-search box ("Joh…" gives "Johannesburg, South Africa") with an **"Everywhere"** option, and is used when location is refused.
- "New in {place}" rows come from where events actually are, not from a fixed list of districts.

### 3. Phone numbers for any country

- Phone fields get a country picker that defaults to the event's country: **+266** (8 digits) or **+27** (9 digits, dropping the leading 0). Others can be added later.
- Numbers are stored with their country code, so WhatsApp, Find my ticket and the "same number, same ticket type" rule work for both.

### 4. Currency per organizer

- Each organization has a currency: **LSL (M)** or **ZAR (R)**. Its prices, tickets, emails and fee invoices use that symbol.
- Fees are the same numbers (4.9% + 7.50 per person), because the loti and the rand are worth the same.
- VENTIQ's money page groups fees by currency, so invoices to SA organizers read in rands.

### 5. Small things

- Terms page: mention South Africa's privacy law (POPIA) alongside Lesotho's.
- WhatsApp templates already work for +27 numbers; nothing to resubmit.
- Time zone: no change (SA and Lesotho are both UTC+2).

## Making sure VENTIQ gets paid

With free or manual tickets, no money passes through VENTIQ, so fees are invoiced as they already are for direct payments:

- Each free or confirmed ticket records its fee at once (already in place).
- The SA organizer's fees appear on the VENTIQ money page under **Fees to invoice**, in rands, with the CSV to send them.
- Recommended for a new market: agree **payment terms up front** (for example, invoice weekly or after each event, due in 7 days). Consider a **deposit or prepaid credit** for the first event, so the first invoice isn't a risk.

## Out of scope for now

- Online payments in SA (PayFast, Ozow or Yoco), rand payouts, an SA business entity or bank account. Revisit only if SA organizers want to sell paid tickets online.

## Order of work

1. Places list and the event form (organizers can say where an SA event is).
2. Phone numbers for +27 (SA attendees can register).
3. Search: distance ranking, "Everywhere" default, type-to-search location.
4. Currency per organizer, and fee invoices in rands.
5. Terms update.

Steps 1 and 2 are what this client needs to launch. Step 3 is what makes SA events easy to find.

## Questions for the client

1. Which towns or cities are their events in?
2. Do any attendees use non-SA, non-Lesotho numbers (for example Eswatini or Botswana)?
3. How often should we invoice them, and how will they pay us (EFT to which account)?
