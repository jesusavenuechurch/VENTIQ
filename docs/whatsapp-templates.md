# WhatsApp templates

All approved by Meta (October 2026) and switched on in `config/constants.php`, except **tickets_sold_out** (section 6), which still needs submitting. Kept here as the record of each template's wording, variables and button, for when one needs changing or resubmitting (WhatsApp Manager → Message templates).

- **Category:** Utility (not Marketing).
- **Language:** English (US). The code sends `en_US`.
- **Variables:** keep `{{1}}`, `{{2}}`… exactly where they are below. The code fills them in that order.
- **Button:** a "Visit website" button with a **dynamic** URL, always `https://ventiq.co.ls/{{1}}`. The code fills in the rest of the address, so the button opens the same page as the email's button. Replace `https://ventiq.co.ls` with the live domain if it's different.

Every template is listed in `config/constants.php` under `whatsapp_templates`. When Meta approves one, set its `'approved' => true` there and deploy; that message starts sending. Until then, people with an email address get the same message by email. If Meta makes you rename a template, or you leave out the button, change its `name` or `button` there too.

---

## The journey: what each message means

Every message answers: what's happening with my ticket, do I need to do
anything, and what happens if I don't.

| Template | Sent when | Ticket | They need to |
|---|---|---|---|
| ticket_registered | They've **said they paid** and it's being checked: proof sent to the organizer, or VENTIQ paid by hand (EcoCash merchant code, M-Pesa, bank transfer). Once per claim. **Not** when a PIN prompt goes out (it may still fail) | Pending, being checked | Nothing; don't pay again |
| ticket_ready | The ticket is active: paid online, confirmed by VENTIQ or the organizer, cash, free or complimentary | Active | Nothing; show it at the door |
| payment_failed | A PIN prompt failed or got no answer and they haven't paid since: after the 3rd try, or 30 minutes after the last | Pending | Try again, pay another way, or send proof if money left their account |
| payment_reminder | ~15 minutes after registering if they haven't started paying (when there's an hour or more left), and halfway through the time to pay. Never while a payment is being made or checked | Pending | Pay before the deadline |
| payment_rejected | The organizer says the payment they were sent never arrived | Pending, place still held | Check it and send it again, or pay another way |
| payment_expired | The time to pay ran out with nothing being checked; the place is released. Button: the event's page | Expired | Get another ticket if they still want to go |

Rules the code keeps so messages never contradict each other:
- Each follow-up looks at the ticket again just before it's sent (active, expired or being paid: skipped).
- A ticket with a payment being checked doesn't expire and isn't reminded (proof sent, by hand to VENTIQ, or a prompt still waiting).
- ticket_ready only once the ticket is active; a group's other tickets only once it's paid in full.
- Each of payment_failed, the 15-minute reminder and the halfway reminder goes once per ticket.

To submit next (see the review of October 2026): ticket_ready with an image header (the ticket's
picture, sent only once it's active), and clearer wording for payment_rejected ("we couldn't verify
your payment with the details sent") and payment_expired ("your reservation has expired… check whether
another ticket is available"). The code picks up a new template by its name in `config/constants.php`.

---

## 1. payment_failed (new)

Sent once per ticket when an online payment fails or gets no answer and the person hasn't paid since: straight away once all 3 tries are used, otherwise 30 minutes after their last try.

- **Name:** `payment_failed`
- **Body:**

  > Hi {{1}}, your payment for {{2}} didn't go through, so your ticket isn't active yet. Your place is held until {{3}}. Tap below to try again, pay another way, or send proof if you did pay.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`, {{3}} `8 Oct, 18:00`
- **Button:** "Finish paying" → `https://ventiq.co.ls/{{1}}` (sample: `ticket/QR-1b2c3d4e/pay`). The code now sends `ticket/<code>/pay/another-way`: the pay page opened on paying directly (the organizer's account, or VENTIQ's merchant code) and sending the reference or a screenshot. The button URL is dynamic, so the approved template doesn't change.

## 2. payment_reminder

Sent halfway through the payment window, while the ticket is still unpaid.

- **Name:** `payment_reminder`
- **Body:**

  > Hi {{1}}, your ticket for {{2}} is held until {{3}}. Pay before then to keep your place. Tap below to pay or send proof of payment.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`, {{3}} `8 Oct, 18:00`
- **Button:** "Pay now" → `https://ventiq.co.ls/{{1}}` (sample: `ticket/QR-1b2c3d4e/pay`). Opens the ticket's pay page.

## 3. payment_rejected

Sent when the organizer says a payment they were sent never arrived.

- **Name:** `payment_rejected`
- **Body:**

  > Hi {{1}}, the organizer of {{2}} couldn't confirm your payment. Your place is still held. Tap below to check your payment and send it again.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`
- **Button:** "Check my payment" → `https://ventiq.co.ls/{{1}}` (sample: `ticket/QR-1b2c3d4e/pay`). Opens the ticket's pay page.

## 4. payment_expired

Sent when the time to pay ran out and the place was released.

- **Name:** `payment_expired`
- **Body (as submitted, after the first version was rejected):**

  > Hi {{1}}, your payment window for the {{2}} ticket has expired. Your ticket is no longer active and the place has been released. No payment is required for this ticket.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`
- **Button:** "Get another ticket", dynamic: the code sends the event's page (`e/<organization>/<event>`).

## 5. payment_submitted (to the organizer)

Sent to the organization's phone when an attendee says they paid the organizer directly. Also sent to VENTIQ's own number (`ventiq_alerts.whatsapp` in `config/constants.php`) when someone says they paid VENTIQ's merchant code by hand; its button then opens the VENTIQ money page.

- **Name:** `payment_submitted`
- **Body:**

  > {{1}} says they paid {{2}} to {{3}}, reference {{4}}. Check your statement, then confirm or reject it.

- **Samples:** {{1}} `Lerato Mokoena`, {{2}} `M250.00`, {{3}} `EcoCash — Events Account`, {{4}} `MP240101.1234.A12345`
- **Button:** "Review payment" → `https://ventiq.co.ls/{{1}}` (sample: `payment-review/12?expires=1760000000&signature=abc`)

---

## 6. tickets_sold_out (to the organizer, new: submit this one)

Sent to the organization's phone once when a ticket type reaches its number of tickets (sales stop there). Raising the number reopens sales; if it sells out again they're told again. Until approved, the organizer's admins get it by email only.

- **Name:** `tickets_sold_out`
- **Body:**

  > Your {{1}} tickets for {{2}} are sold out ({{3}} taken). If you can take more people, you can allow more tickets on your event and sales reopen straight away.

- **Samples:** {{1}} `VIP`, {{2}} `Maseru Youth Summit`, {{3}} `20`
- **Button:** "Allow more tickets" → `https://ventiq.co.ls/{{1}}` (sample: `organizer/events/12/edit`)

Once Meta approves it, set `'approved' => true` for `tickets_sold_out` in `config/constants.php` and deploy.

---

## Already with Meta, leave as they are

- **ticket_ready:** sent once a ticket is paid. Already approved and live.
- **ticket_registered:** sent when the first payment is started. It's already submitted with {{1}} name, {{2}} event, {{3}} ticket number and {{4}} amount, and the code sends those four.
