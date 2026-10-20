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
3. **Minutes format** for meetings (and the "Next item" wording).
4. **Participant list PDF** for procurement (per day for multi-day).
5. **Programme enrolment and automatic certificates.**
6. **Certificate designer and stored signatures.**
7. **Ask Ventiq over the organization's memory** (search, then answers with
   sources).
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
