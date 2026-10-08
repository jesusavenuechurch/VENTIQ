# Maps, marathons and partners: plan

**Status:** plan only, nothing built. Written from how VENTIQ works today (October 2026).
**Recommendation, in order:** (1) a "Get directions" button now; (2) gather marathon requirements; (3) custom registration questions, then map pins, route maps and nearby search together with the location / South Africa work; (4) venue and sound partners handled by hand until demand shows.

## 1. Maps on the event page

**Today:** an event stores a district (`city`), a venue name (`venue`) and an optional free-text address (`location`). No map coordinates. The event page and the ticket show these as text only.

**Step 1: "Get directions" button (about half a day).**
- A Google Maps link built from the venue and address the event already has (`https://www.google.com/maps/search/?api=1&query=…`). No API key, no cost.
- Shown on the event page, the ticket page and the "your ticket is ready" message.
- Limit: only as good as what the organizer typed ("Lehakoe Centre, Maseru" works; "behind the church" doesn't).

**Step 2: the organizer drops a pin (a few days).**
- A small map in the event form; the organizer drags a pin to the exact spot. Store `latitude` and `longitude` on the event.
- Event page: a small map and a "Directions" button to the exact point (`https://www.google.com/maps/dir/?api=1&destination=lat,lng`), which opens Google Maps on the phone.
- OpenStreetMap tiles via Leaflet are enough; no Google billing.
- These are the coordinates `docs/south-africa-plan.md` needs for "events near you", so do this together with that work.

## 2. Marathons and races

**Already fits:**

| Race need | In VENTIQ today |
|---|---|
| Distances (5 km, 10 km, 21 km), each with a price and a limit | Ticket types, with the required number of tickets |
| A personal entry with a QR | A ticket per person, with its holder's name |
| Race-pack and bib collection | The scanner app |
| Expected entries and fees up front | The order summary on the event form |
| Clubs and teams | Group tickets (but see below) |

**Missing:**
- **Runner details:** T-shirt size, date of birth or age category, gender, emergency contact, medical notes, club. VENTIQ has a fixed set of extra fields for workshops only. The real fix is **custom registration questions** the organizer sets per event, which also helps workshops, conferences and corporate events.
- **Bib numbers:** formatted ticket numbers per distance (e.g. 10K-0142), or assigned in a batch before race day.
- **Route map:** upload the course file (GPX from Strava or Garmin); the event page shows the route, start, finish and water points, with distance and climb. Builds on the map in section 1.
- **Waiver:** a per-event "I accept the risks" text the runner must accept, besides the general terms.
- **Group entries:** a group ticket admits several people under one name; races need each runner's own details.
- **Timing and results:** a separate business (chips, mats). VENTIQ should not time races. At most, import the official results file and show each runner's time.

**Next step:** requirements gathering with the organizers. See `docs/marathon-questions.md`. Their answers decide whether this is "custom questions + route map" (a couple of weeks) or a race-specific product.

## 3. Venue and sound partners

**Worth it because:** VENTIQ becomes where an event is planned, not just where tickets are sold; partners can pay a referral or listing fee; it fits the "we set it up for you" service.

**Early because:** it's a two-sided marketplace (enough providers and enough organizers), each listing needs availability, quotes and deposits, and quality problems ("the sound failed") land on VENTIQ's name. None of it exists in the code; it would be new models for providers, listings, availability and booking requests. And it draws attention away from proving the ticketing with real paid events.

**Start by hand:** offer "Need a venue or sound? We'll connect you" alongside setting events up for clients. Agree a referral fee with the two providers. Optionally a simple, curated "Partners" page with a WhatsApp button for each. Count how many organizers ask; build listings and booking only when requests repeat and there are more than a handful of providers.
