# Recording and transcription (VENTIQ Sessions)

People in Lesotho often don't type fast enough to keep notes, so they go back to paper. The answer: put a phone on the table and record; VENTIQ writes it down.

## Decisions (October 2026)

- **Hybrid.** The phone records in 30-second pieces and uploads each one as it goes, keeping it on the phone until it has arrived. The Mac mini transcribes each piece as it lands, so the transcript shows in the console about a minute behind the room. A bad connection only means it catches up at the end; nothing is lost.
- **Fits the agenda.** Each agenda item has a start and end time, so the transcript goes under the item that was running. Typed notes keep working beside it. Ventiq Assist picks key points and action points from both, and the minutes and report are made as they are today.
- **Runs on our Mac mini**, beside Ollama. Audio never leaves our machine.
- **English first, with Whisper.** Sesotho, and Sesotho mixed with English, later: test Meta's MMS model (it has Sesotho) on real recordings, then decide.
- **Audio is deleted once the report is marked reviewed.** Only the text is kept.
- **Part of the existing plans** for now. Pricing is revised later. A fair-use limit per session protects the Mac mini.
- **The room is told.** A "This session is being recorded" notice when recording starts (Data Protection Act 2011).

## Steps

1. **Mac mini test** (below): speed and accuracy on real recordings.
2. **Scenarios**: walk through how each kind of session uses recording before building.
3. **Build**: record button (keeps the screen awake), 30-second uploads with retry, a transcription queue, the transcript under each item and editable on the report, Assist reading it when the session finishes, audio deleted on review.
4. **Near-live view**: the rolling transcript and sticky notes in the console.
5. **Sesotho**: test MMS; possibly adapt a model with recordings collected with consent.

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

## Recordings to test with

Record with everyone's agreement, 10 to 15 minutes each, on an ordinary phone (voice memo is fine).

1. A small meeting, phone in the middle of the table, English.
2. A big room, phone at one end of the table.
3. A meeting in Sesotho and English mixed.
4. Background noise: a fan, a generator, traffic, a busy office.
5. People talking over each other, or a heated discussion.
6. One presenter with a microphone and a PA system (a workshop or presentation).
