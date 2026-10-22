# VENTIQ Sessions: what it's for, and the plan

Sessions is an organization's memory: every meeting, workshop and training
written down well, kept, and searchable, with the follow-ups chased.

**Where the AI runs.** The app runs on its server (anywhere); the model runs
on VENTIQ's Mac mini in Lesotho, reached over Tailscale (`OLLAMA_URL` in
`config/ai.php`), so what's said in a meeting is processed in the country.
The model holds nothing: the records stay in the app's database, and for
each question the app finds the relevant ones and sends them with it
(retrieval-augmented generation). So:

- When the Mac mini is off or unreachable, AI work waits and retries
  (queued jobs), and pages say the report is on its way; nothing is lost.
- Search indexes (embeddings) are made by the Mac mini but stored in the
  app's database, next to the records they point to.
- Only one organization's records are ever sent with its own questions.
- The OpenRouter provider in `config/ai.php` sends text abroad; it stays
  off in production.

## The three settings it's built for

| | A meeting (scrum, management, board) | A workshop | A training (programme) |
|---|---|---|---|
| Happens | Again and again: weekly, monthly | Once, a day or two | Over several days |
| Its parts | Agenda items | Modules, presenters | Days, modules |
| What it produces | **Minutes**: who came, apologies, each item's discussion, decisions, action points with owner and date | A **report** of what was covered, and a **participant list PDF** for procurement, venue payment, reimbursement and per diem | Attendance for each day, and **certificates** for those who qualify |
| What carries on | Action points to the next meeting; last minutes to approve | Nothing | Attendance from day to day |

## Decisions

1. **A session's parts are agenda items** (with who leads each, optional).
   Presentations are the case where each item is a person. The live screen
   says "Next item" for meetings, "Next presenter" for presentations.
2. **Recurring sessions are a series.** "Weekly ops meeting, Mondays 9:00"
   makes each meeting ahead of time from the series: same name, type,
   agenda and people. Each meeting opens with the action points still open
   from the previous one, and the previous minutes to approve. Changing one
   meeting doesn't change the series; changing the series changes the
   meetings not yet held.
3. **Action points are their own records**: what, who owns it, by when,
   which meeting it came from, done or not. The AI proposes them from the
   notes when the meeting ends; the organizer confirms or edits them when
   reviewing. Owners get a reminder before the due date, and the list comes
   up again at the next meeting of the series until each is done.
4. **Meeting minutes are a format, not just "a report".** Fixed sections in
   a set order, editable, signed off when reviewed, downloadable as PDF.
5. **The participant list PDF is a document for officials**: the
   organization and session, date and venue, and one row per person (name,
   organization, position, phone, email, signature). For workshops over
   several days, one column per day. Stamped with how it was produced
   (self check-in, time) so it can stand as evidence.
6. **A programme is a course people enrol in once.** Its days are its
   sessions; attendance is counted across them; the certificate rule is set
   per programme (default: every day). When someone meets it, their
   certificate is issued and sent without anyone pressing a button.
7. **Certificates have a designer**, one per organization (and optionally
   per programme): logo, title, wording with the person's name, programme
   and dates, and up to two signatories. A person allowed to sign draws
   their signature once in VENTIQ; it's applied to every certificate until
   they change it. Who may sign is a permission on the team.
8. **Ventiq Assist answers from the organization's own records.** "What did
   we discuss about logistics this month?" searches minutes, reports and
   action points, and answers with links to the meetings it came from. It
   only ever sees the asking organization's records.
9. **Transcription feeds the same notes.** Spoken words become note lines
   under the agenda item that's on, so everything above works whether notes
   are typed or heard.

## Order to build

1. **Recurring series**, and each meeting linked to the one before. *Done:*
   `SessionSeries`, `SeriesScheduler` (4 ahead, topped up daily by
   `sessions:top-up-series`), weekly, fortnightly, monthly by date or by
   weekday. A session now counts against the plan when it starts.
2. **Action points**: extracted, confirmed, carried to the next meeting,
   reminders. *Done:* `ActionPoint`; `ExtractActionPoints` (after each
   report, retried while the Mac mini is unreachable) proposes them;
   keep/change/remove on the report, reviewing keeps the rest; "From last
   time" on a series' next meeting; the Actions tab; reminder emails two
   days before, on the day and weekly when overdue
   (`sessions:action-reminders`). WhatsApp reminders wait for a template.
3. **Minutes format** for meetings (and the "Next item" wording). *Done:*
   meetings, board and committee meetings (`SessionType::takesMinutes()`)
   are scheduled with a chair, secretary, venue and agenda (each item led
   by someone, optional; a series keeps them as its standing agenda). The
   console says "Agenda" and "Next item". Ventiq Assist writes each item
   up in minute style (past tense, third person), and `MinutesBuilder`
   assembles the minutes: present, apologies, previous minutes, matters
   arising, each item's discussion and decisions, action points, any other
   business, next meeting. Every part can be changed in place (kept on
   `capture_sessions.minutes`); confirming them is reviewing the report,
   signed off in the reviewer's name. The PDF is the minutes.
4. **Participant list PDF** for procurement (per day for multi-day). *Done:*
   `ParticipantList` makes it for a session (Check-in, Report) or a whole
   programme (its page), landscape A4: one row per person (name,
   organisation, position, district when given, phone, email, signature
   drawn in the scanner app), a column per day with the time in and how it
   was recorded (Self, Desk, Scanner), blank rows to fill in by hand
   (`?blank=`, default 5), a note on how it was produced and a "certified
   by" line. Self check-ins are now recorded as `self`, not `walk_in`.
5. **Programme enrolment and automatic certificates.** *Done:*
   `programme_enrolments`: people enrol by the programme's link
   (`/enrol/{token}`, closable, emails the days), the organizer adds them
   (one, or a list pasted from a spreadsheet), and checking in to a day
   enrols too; withdrawing keeps their attendance. The rule is every day
   (default) or at least N days (`events.certificate_min_days`).
   `ProgrammeProgress` shows each person's days and standing (certified,
   meets the rule, on track, missed too many) and issues certificates to
   whoever meets it, emailed with the PDF: when a day ends (a `Session`
   status change) and each evening at 18:50
   (`sessions:issue-certificates`) for days only checked in to. A rule
   change that would issue some asks first. Certificate QR codes fall back
   to SVG without Imagick (`App\Support\Qr`).
6. **Certificate designer and stored signatures.** *Done:* Programmes,
   Certificate design (`CertificateDesign`): banner, classic or minimal
   layout, colours, title, wording with `{programme}` `{organization}`
   `{venue}` `{dates}` `{days}`, the logo, and up to two signatories; one
   for the organization and optionally one per programme, with a live
   preview from the PDF's own view. An Admin allows people to sign on the
   Team page (`users.can_sign_certificates`); each draws their signature
   once under My signature (any role), kept on the private disk and never
   overwritten. Each certificate keeps a snapshot of its design and
   signatures (`certificates.design`), so later changes don't alter
   certificates already out.
7. **Ask Ventiq over the organization's memory** (search, then answers with
   sources). *Done:* `memory_chunks` holds each held session as passages:
   each part's write-up (for a meeting, its minutes as confirmed or
   edited), the notes as typed, the attendance and the action points with
   their status (`MemoryIndexer`, run again on every change; a passage
   whose words didn't change keeps its embedding). Embeddings come from the
   Mac mini (`OLLAMA_EMBED_MODEL`, default `nomic-embed-text`) and are
   stored here, normalised float32; passages wait when it's off and the
   hourly `sessions:index-memory` embeds them. A question is searched by
   meaning and by words (full-text), merged; its dates are read from the
   words ("this month", "in March"); the counts and, when asked, the open
   action points are added; one call to the model writes the answer,
   citing the sessions, which the panel links to. Only the asking
   organization's records, and only for roles that read reports.

   **To switch on:** on the Mac mini, `ollama pull nomic-embed-text`; after
   deploying, `php artisan migrate` and `php artisan sessions:index-memory
   --all` once. At a few thousand passages per organization the
   similarity is worked out in PHP; past that, a vector index (MariaDB
   11.7's VECTOR type) is the next step.
8. **Transcription** (a phone in the room, Whisper on the local server).

## What exists already, to build on

- Session types with their own roles and report sections
  (`App\Support\SessionType`).
- `capture_sessions.parent_session_id`, and `segment_action_items` /
  `segment_open_issues` tables: the start of follow-ups, never used yet.
- Check-in by QR or code; attendance cards; programme certificates issued
  by hand, a single fixed design (`certificates/certificate.blade.php`).
- Ventiq Assist chat (`App\Services\Assist\AssistChat`), which today
  answers about how to use VENTIQ, not from an organization's records.

## Paying for Sessions

- **Plans** (the organization buys them on Plan, paid through Pay Lesotho):
  Free, 1 session a month; Team, 3 for M500; Business, 6 for M1,200
  (Programmes). Pay-as-you-go sessions on top (1 for M150, 3 for M400,
  less each from 4). A session counts when it starts.
- **Corporate rates** (agreed with VENTIQ, set by a super admin on
  Corporate rates): a price per session and a number free each month (1
  by default). The organization is never stopped by a quota. Each session
  is priced when it starts (`capture_sessions.charge`); on the 1st at
  06:10 `sessions:invoice` puts last month's on one invoice
  (`session_invoices`, VQS-2026-0001), raised in Khoebo (product
  VQ-SESSION, one line per session billed) and emailed with the PDF to the
  billing email and the organization's Admins, due in 14 days. A month of
  only free sessions isn't invoiced. If Khoebo is down the invoice still
  goes out and can be raised in Khoebo later. Payments are recorded by a
  super admin, in Khoebo too. Ending a rate puts the organization back on
  its plan; sessions already held are still invoiced.
- Set `VENTIQ_INVOICE_PAYMENT_INSTRUCTIONS` (bank account, mobile money
  number) for the "How to pay" box, and run `php artisan khoebo:products`
  once on each Khoebo to make VQ-SESSION.

