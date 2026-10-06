# WhatsApp templates

All approved by Meta (October 2026) and switched on in `config/constants.php`. Kept here as the record of each template's wording, variables and button, for when one needs changing or resubmitting (WhatsApp Manager → Message templates).

- **Category:** Utility (not Marketing).
- **Language:** English (US). The code sends `en_US`.
- **Variables:** keep `{{1}}`, `{{2}}`… exactly where they are below. The code fills them in that order.
- **Button:** a "Visit website" button with a **dynamic** URL, always `https://ventiq.co.ls/{{1}}`. The code fills in the rest of the address, so the button opens the same page as the email's button. Replace `https://ventiq.co.ls` with the live domain if it's different.

Every template is listed in `config/constants.php` under `whatsapp_templates`. When Meta approves one, set its `'approved' => true` there and deploy; that message starts sending. Until then, people with an email address get the same message by email. If Meta makes you rename a template, or you leave out the button, change its `name` or `button` there too.

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
- **Button:** none. The email version still links to the event page so they can register again.

## 5. payment_submitted (to the organizer)

Sent to the organization's phone when an attendee says they paid the organizer directly. Also sent to VENTIQ's own number (`ventiq_alerts.whatsapp` in `config/constants.php`) when someone says they paid VENTIQ's merchant code by hand; its button then opens the VENTIQ money page.

- **Name:** `payment_submitted`
- **Body:**

  > {{1}} says they paid {{2}} to {{3}}, reference {{4}}. Check your statement, then confirm or reject it.

- **Samples:** {{1}} `Lerato Mokoena`, {{2}} `M250.00`, {{3}} `EcoCash — Events Account`, {{4}} `MP240101.1234.A12345`
- **Button:** "Review payment" → `https://ventiq.co.ls/{{1}}` (sample: `payment-review/12?expires=1760000000&signature=abc`)

---

## Already with Meta, leave as they are

- **ticket_ready:** sent once a ticket is paid. Already approved and live.
- **ticket_registered:** sent when the first payment is started. It's already submitted with {{1}} name, {{2}} event, {{3}} ticket number and {{4}} amount, and the code sends those four.
