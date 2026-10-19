# EcoCash payments by SMS

Attendees pay VENTIQ, never the organizer, except on events VENTIQ sponsors.
Every payment into VENTIQ's EcoCash merchant wallet makes EcoCash (sender
**199**) send an SMS to the merchant SIM. The phone holding the SIM forwards
each one to VENTIQ, and VENTIQ confirms the payment and sends the ticket.

## How a payment is matched

The payment page tells the attendee to pay merchant code `PAYLESOTHO_ECOCASH_MERCHANT_CODE`,
the amount owed, and to type the four characters of their entry code as
the reference (`X82L` for `VQ-X82L`). When the SMS arrives, VENTIQ tries, in order:

1. **"I've paid".** An attendee who said they paid by hand, from this number
   or quoting this approval code.
2. **An online payment.** A PIN prompt to the same number, for the same amount,
   up to 30 minutes before. The SMS confirms it, and completes it if PayLesotho
   never answered.
3. **The reference.** The ticket whose entry code the payer typed.
4. **The number.** The one waiting ticket registered to the paying number that
   owes exactly this amount.

A match activates the ticket as an online sale. VENTIQ holds the money, and
the ticket's payout line, with fees off, goes into the weekly payout.

Anything else is listed on **VENTIQ money → EcoCash payments to check**, to
match by entry code or set aside: no match, an amount that isn't what the
ticket owes, or several tickets that could be it. When an attendee later
says "I've paid" from another phone, a waiting payment from that phone is
matched straight away.

## The balance check

Each payment SMS shows the wallet's new balance. VENTIQ acts on a payment
only when that balance is the last one plus this payment, minus any money
out since. Withdrawals ("Stock Reimbursement ... Txn ID") are forwarded
too, for exactly this reason; EcoCash doesn't charge VENTIQ for them. A
made-up SMS can't know the balance, so it's held: check it on the EcoCash
statement, then press **It's on the statement**. Messages that arrive late
are put back in order on their own.

## Setting up the tablet

1. Install an SMS forwarder app. Any app that posts incoming SMS to a web
   address works, for example the open-source **SMS to URL Forwarder**.
2. Set it to forward messages **from 199** only.
3. Set the URL to `https://ventiq.co.ls/api/mobile-money/sms`, method POST.
4. Add the header `X-Ventiq-Token: <MOBILE_MONEY_SMS_TOKEN>`. If the app
   can't send headers, add `?token=<MOBILE_MONEY_SMS_TOKEN>` to the URL instead.
5. Send JSON with the sender, the text and the time. Common names are all
   understood (`from`/`sender`, `text`/`message`/`body`,
   `receivedStamp`/`timestamp`), for example:

   ```json
   {"from": "%from%", "text": "%text%", "receivedStamp": "%receivedStamp%", "device": "tablet"}
   ```

6. Turn off battery saving for the app, keep the tablet charging, and keep
   Wi-Fi or mobile data on. Messages received while it's offline are
   forwarded when it reconnects; each is only counted once.

Test it by paying the merchant M1 from another phone: the payment shows on
the money page within seconds. That page also says when the tablet last
forwarded something, and turns red after `MOBILE_MONEY_QUIET_HOURS`.

## Not yet

- **M-Pesa** to VENTIQ by hand needs VENTIQ's M-Pesa number on the payment
  page, and later its own phone forwarding its SIM's SMS. M-Pesa online stays
  off meanwhile (`PAYLESOTHO_MPESA_ENABLED=false`).
- **Cash and bank transfers** to VENTIQ aren't matched by SMS. Cash an
  organizer takes themselves is invoiced as before.
