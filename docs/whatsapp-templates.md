# WhatsApp templates to submit to Meta

Submit each one in WhatsApp Manager → Message templates → Create template.

- **Category:** Utility (not Marketing).
- **Language:** English (US). The code sends `en_US`.
- **Variables:** keep `{{1}}`, `{{2}}`… exactly where they are below. The code fills them in that order.
- **Button:** a "Visit website" button with a **dynamic** URL, always `https://ventiq.co.ls/{{1}}`. The code fills in the rest of the address, so the button opens the same page as the email's button. Replace `https://ventiq.co.ls` with the live domain if it's different.

Once Meta approves a template, put its name in `.env`; that message starts sending straight away. Until then, people with an email address get the same message by email.

---

## 1. payment_failed (new)

Sent once per ticket, 10 minutes after an online payment fails or gets no answer, if the person hasn't paid since.

- **Name:** `payment_failed`
- **Body:**

  > Hi {{1}}, your payment for {{2}} didn't go through, so your ticket isn't active yet. Your place is held until {{3}}. Tap below to try again, pay another way, or send proof if you did pay.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`, {{3}} `8 Oct, 18:00`
- **Button:** "Finish paying" → `https://ventiq.co.ls/{{1}}` (sample: `ticket/QR-1b2c3d4e/pay`). Opens the ticket's pay page.
- **.env:** `WHATSAPP_TEMPLATE_PAYMENT_FAILED=payment_failed`

## 2. payment_reminder

Sent halfway through the payment window, while the ticket is still unpaid.

- **Name:** `payment_reminder`
- **Body:**

  > Hi {{1}}, your ticket for {{2}} is held until {{3}}. Pay before then to keep your place. Tap below to pay or send proof of payment.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`, {{3}} `8 Oct, 18:00`
- **Button:** "Pay now" → `https://ventiq.co.ls/{{1}}` (sample: `ticket/QR-1b2c3d4e/pay`). Opens the ticket's pay page.
- **.env:** `WHATSAPP_TEMPLATE_PAYMENT_REMINDER=payment_reminder`

## 3. payment_rejected

Sent when the organizer says a payment they were sent never arrived.

- **Name:** `payment_rejected`
- **Body:**

  > Hi {{1}}, the organizer of {{2}} couldn't confirm your payment. Your place is still held. Tap below to check your payment and send it again.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`
- **Button:** "Check my payment" → `https://ventiq.co.ls/{{1}}` (sample: `ticket/QR-1b2c3d4e/pay`). Opens the ticket's pay page.
- **.env:** `WHATSAPP_TEMPLATE_PAYMENT_REJECTED=payment_rejected`

## 4. payment_expired

Sent when the time to pay ran out and the place was released.

- **Name:** `payment_expired`
- **Body:**

  > Hi {{1}}, the time to pay for your {{2}} ticket has run out, so your place was released. If places are still available you can register again, or contact the organizer.

- **Samples:** {{1}} `Lerato`, {{2}} `Maseru Youth Summit`
- **Button:** "Register again" → `https://ventiq.co.ls/{{1}}` (sample: `e/myn/maseru-youth-summit`). Opens the event page, where they can register afresh if places are left.
- **.env:** `WHATSAPP_TEMPLATE_PAYMENT_EXPIRED=payment_expired`

## 5. payment_submitted (to the organizer)

Sent to the organization's phone when an attendee says they paid the organizer directly.

- **Name:** `payment_submitted`
- **Body:**

  > {{1}} says they paid {{2}} to {{3}}, reference {{4}}. Check your statement, then confirm or reject it.

- **Samples:** {{1}} `Lerato Mokoena`, {{2}} `M250.00`, {{3}} `EcoCash — Events Account`, {{4}} `MP240101.1234.A12345`
- **Button:** "Review payment" → `https://ventiq.co.ls/{{1}}` (sample: `payment-review/12?expires=1760000000&signature=abc`)
- **.env:** `WHATSAPP_TEMPLATE_PAYMENT_SUBMITTED=payment_submitted`

---

## Already with Meta, leave as they are

- **ticket_ready:** sent once a ticket is paid. Already approved and live.
- **ticket_registered:** sent when the first payment is started. It's already submitted with {{1}} name, {{2}} event, {{3}} ticket number and {{4}} amount, and the code sends those four.
