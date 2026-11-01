# Sesotho test (Mac mini)

Whisper doesn't know Sesotho. This tries three free models from Meta, all run on the Mac mini:

1. **MMS language ID** (`facebook/mms-lid-126`): is each 30-second piece Sesotho or English?
2. **MMS speech recognition** (`facebook/mms-1b-all`, Sesotho): what was said, in Sesotho. No punctuation.
3. **NLLB translation** (`facebook/nllb-200-distilled-600M`): that Sesotho in English, for the minutes.

The first run downloads the models (about 8 GB in all). After that nothing leaves the machine.

## Set up (once)

```sh
python3 -m venv ~/mms
~/mms/bin/pip install torch transformers sentencepiece soundfile numpy
```

## Run

```sh
cd ~/whisper.cpp
ffmpeg -i recordings/sesotho1.m4a -ar 16000 -ac 1 -c:a pcm_s16le recordings/sesotho1.wav
~/mms/bin/python ~/VENTIQ/tools/sesotho-test/mms_test.py recordings/sesotho1.wav --translate
```

(Point the last line at wherever this file is on the Mac, or copy `mms_test.py` next to the recordings.)

It writes `sesotho1-sesotho.txt` (each piece's Sesotho, with the languages it heard, e.g. `[sot 91%, eng 4%, ...]`) and `sesotho1-english.txt`, and prints how long each step took.

## What to look at

- **The language line:** does it say `sot` for the Sesotho pieces and `eng` for the English ones?
- **The Sesotho:** roughly how many words in 10 are right? Names and places?
- **The English:** does it say what was meant?
- **Mixed pieces:** how badly do the English words inside Sesotho come out (and the reverse)?
- **Time:** for the length of the recording.

Recordings to try, with everyone's agreement, 5 to 10 minutes each: one all in Sesotho, one mixed the way meetings really go, and `meeting3.wav` from the earlier test.
