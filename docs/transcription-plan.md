# Recording and transcription (VENTIQ Sessions)

People in Lesotho often don't type fast enough to keep notes, so they go back to paper. The answer: put a phone on the table and record; VENTIQ writes it down.

## Decisions (October 2026)

- **Hybrid.** The phone records in 30-second pieces and uploads each one as it goes, keeping it on the phone until it has arrived. The Mac mini transcribes each piece as it lands, so the transcript shows in the console about a minute behind the room. A bad connection only means it catches up at the end; nothing is lost.
- **Fits the agenda.** Each agenda item has a start and end time, so the transcript goes under the item that was running. Typed notes keep working beside it. Ventiq Assist picks key points and action points from both, and the minutes and report are made as they are today.
- **Runs on our Mac mini**, beside Ollama. Audio never leaves our machine.
- **English first, with Whisper.** Sesotho, and Sesotho mixed with English, later: test Meta's MMS model (it has Sesotho) on real recordings, then decide.
- **Audio is kept for now.** Deleting it once the report is marked reviewed (keeping the text) is one switch: `RECORDING_DELETE_AUDIO_ON_REVIEW=true`.
- **Part of the existing plans** for now. Pricing is revised later. A fair-use limit per session protects the Mac mini.
- **The room is told.** A "This session is being recorded" notice when recording starts (Data Protection Act 2011).

## Steps

1. **Mac mini test** (below): speed and accuracy on real recordings.
2. **Scenarios**: walk through how each kind of session uses recording before building.
3. **Build**: record button (keeps the screen awake), 30-second uploads with retry, a transcription queue, the transcript under each item and editable on the report, Assist reading it when the session finishes, audio deleted on review.
4. **Near-live view**: the rolling transcript and sticky notes in the console.
5. **Sesotho**: test MMS; possibly adapt a model with recordings collected with consent.

## Phase 1: built (English, one person speaking at a time)

How it works:

- **Console.** When Whisper is set up, a live session shows **Record**. It first asks that the room is told, then records. **Pause** pauses the session and the recording together (the transcript notes when). **Next item** starts a new piece, so what's said from then goes under the next item. **Finish** waits until every piece has reached the server ("Sending the last pieces…").
- **The phone.** Pieces are 30 seconds, each a whole file, kept in the browser's storage until the server has them: a dropped connection or a closed page only delays them; they're sent the next time the console is opened on that phone. The screen is kept awake while recording. One phone records a session at a time; another is told someone else is recording.
- **Server.** `POST /sessions/{id}/recording/pieces` stores the piece (`storage/app/private/recordings/session-{id}/`), and the `TranscribeRecordingPiece` job sends it to Whisper with the session's hint list: organisation, title, agenda items, who leads them and who checked in. Whisper's text is cleaned (its `[BLANK_AUDIO]` tags, a sentence repeated straight after itself, a piece that's only "Thank you.") and goes under the item.
- **The report** waits for the last pieces, then Ventiq Assist writes it from the typed notes and the transcript together (`SessionSegment::notesText()`). A Transcript tab on the report shows it item by item, correctable; a corrected item isn't overwritten. Admins and Staff only: Viewers and the check-in desk don't see it.
- **Safety net.** `sessions:recording-catch-up` (every 10 minutes) sends stuck pieces again, and writes a report that has waited 45 minutes for its last pieces from what's there.
- **Fair use:** 4 hours per session (`RECORDING_MAX_SECONDS`).

### Switching it on in production

1. Run the migrations (`2026_10_29_000008` adds the recording pieces and each item's transcript).
2. On the Mac mini, keep Whisper running (step 3 below) on its Tailscale address, with `-l en -mc 0 --convert`.
3. In the server's `.env`: `WHISPER_URL=http://<the Mac mini's Tailscale address>:8178`. Until it's set, the Record button doesn't show.
4. A queue worker must be running (`php artisan queue:work`), as for the reports, and the scheduler (`php artisan schedule:run` every minute) for the safety net.

## Mac mini test (M4, 10-core CPU and GPU, 24 GB)

whisper.cpp runs Whisper on the Mac's GPU. 24 GB holds both Whisper and the Ollama model.

### 1. Install

```sh
brew install cmake ffmpeg
git clone https://github.com/ggml-org/whisper.cpp
cd whisper.cpp
cmake -B build
cmake --build build -j --config Release
sh ./models/download-ggml-model.sh large-v3-turbo
sh ./models/download-ggml-model.sh large-v3
```

`large-v3-turbo` (about 1.6 GB) is the fast one we expect to use. `large-v3` (about 3 GB) is slower and sometimes more accurate; we compare the two.

### 2. Transcribe a recording

Convert any phone recording (m4a, mp3, ogg) to what Whisper reads, then run it and time it:

```sh
ffmpeg -i meeting.m4a -ar 16000 -ac 1 -c:a pcm_s16le meeting.wav
time ./build/bin/whisper-cli -m models/ggml-large-v3-turbo.bin -f meeting.wav -l en -otxt -ovtt -of meeting-turbo
time ./build/bin/whisper-cli -m models/ggml-large-v3.bin -f meeting.wav -l en -otxt -ovtt -of meeting-large
```

This writes `meeting-turbo.txt` and `.vtt` (with times). For the mixed Sesotho recording, also try `-l auto`.

### 3. Run it as a service

The app will send audio to Whisper the way it talks to Ollama:

```sh
./build/bin/whisper-server -m models/ggml-large-v3-turbo.bin --host 127.0.0.1 --port 8178 --convert
curl 127.0.0.1:8178/inference -F file=@meeting.m4a -F response_format=verbose_json -F language=en
```

Keep it on `127.0.0.1` for the test. When we build, it is reached the same way the app reaches Ollama today, never open to the internet.

### 4. Write down for each recording

| | |
|---|---|
| Recording length | |
| Time taken, turbo / large-v3 | |
| Accuracy: read a 2-minute stretch; roughly how many words in 10 are right | |
| Names and places right? (people, villages, organisations) | |
| Time taken while Ollama is writing a report at the same time | |

## Results so far

On the Mac mini (M4, 24 GB), `-t 8`:

| Recording | Length | Model | Time | Notes |
|---|---|---|---|---|
| Voice note, one speaker | 27 s | turbo / large-v3 | 2.6 s / 4.2 s | Every word right with large-v3. |
| Monologue, one speaker | 5 min | turbo with hint list | 18.6 s (about 16x real time) | "Ventiq" right everywhere with the hint; "roster" and "purchase" still wrong. |

- **Model: turbo.** About as accurate as large-v3 on these, twice as fast, and its lines follow sentences.
- **Hint list (`--prompt`):** pass the organisation, agenda items, facilitators and checked-in names for each session. It fixes names; it does little for ordinary words, which Ventiq Assist corrects when writing the minutes.
- **Speed:** a 30-second piece takes under 2 seconds; a 2-hour meeting about 7 minutes if it all arrives at the end.
- **Still to test:** several people talking, and Sesotho mixed with English.

## Recordings to test with

Record with everyone's agreement, 10 to 15 minutes each, on an ordinary phone (voice memo is fine).

1. A small meeting, phone in the middle of the table, English.
2. A big room, phone at one end of the table.
3. A meeting in Sesotho and English mixed.
4. Background noise: a fan, a generator, traffic, a busy office.
5. People talking over each other, or a heated discussion.
6. One presenter with a microphone and a PA system (a workshop or presentation).
