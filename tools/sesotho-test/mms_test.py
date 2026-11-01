"""
Sesotho test for VENTIQ Sessions, run on the Mac mini (see README.md here).

For a 16 kHz mono WAV recording, in 30-second pieces:
  1. which language each piece is (Meta MMS language ID),
  2. what was said in Sesotho (Meta MMS speech recognition, Sesotho),
  3. that Sesotho in English (Meta NLLB translation).

Writes <name>-sesotho.txt (with the language of each piece), and
<name>-english.txt when --translate is given. Everything runs on this
machine; nothing is sent anywhere once the models are downloaded.
"""
import argparse
import time

import numpy as np
import soundfile as sf
import torch

PIECE = 30 * 16000


def device():
    return torch.device("mps" if torch.backends.mps.is_available() else "cpu")


def load(path):
    audio, rate = sf.read(path, dtype="float32")
    if audio.ndim > 1:
        audio = audio.mean(axis=1)
    if rate != 16000:
        raise SystemExit(f"{path} is {rate} Hz. Convert it first: ffmpeg -i in.m4a -ar 16000 -ac 1 out.wav")
    return [audio[i:i + PIECE] for i in range(0, len(audio), PIECE) if len(audio[i:i + PIECE]) > 8000]


def languages(pieces, dev):
    from transformers import AutoFeatureExtractor, Wav2Vec2ForSequenceClassification

    name = "facebook/mms-lid-126"
    extractor = AutoFeatureExtractor.from_pretrained(name)
    model = Wav2Vec2ForSequenceClassification.from_pretrained(name).to(dev).eval()
    found = []
    for piece in pieces:
        inputs = extractor(piece, sampling_rate=16000, return_tensors="pt").to(dev)
        with torch.no_grad():
            probs = torch.softmax(model(**inputs).logits[0], dim=-1)
        top = torch.topk(probs, 3)
        found.append([(model.config.id2label[int(i)], float(p)) for p, i in zip(top.values, top.indices)])
    return found


def sesotho(pieces, dev):
    from transformers import AutoProcessor, Wav2Vec2ForCTC

    name = "facebook/mms-1b-all"
    processor = AutoProcessor.from_pretrained(name, target_lang="sot")
    model = Wav2Vec2ForCTC.from_pretrained(name, target_lang="sot", ignore_mismatched_sizes=True).to(dev).eval()
    texts = []
    for piece in pieces:
        inputs = processor(piece, sampling_rate=16000, return_tensors="pt").to(dev)
        with torch.no_grad():
            ids = torch.argmax(model(**inputs).logits, dim=-1)[0]
        texts.append(processor.decode(ids))
    return texts


def english(texts, dev):
    from transformers import AutoModelForSeq2SeqLM, AutoTokenizer

    name = "facebook/nllb-200-distilled-600M"
    tokenizer = AutoTokenizer.from_pretrained(name, src_lang="sot_Latn")
    model = AutoModelForSeq2SeqLM.from_pretrained(name).to(dev).eval()
    out = []
    for text in texts:
        if not text.strip():
            out.append("")
            continue
        inputs = tokenizer(text, return_tensors="pt", truncation=True, max_length=400).to(dev)
        with torch.no_grad():
            tokens = model.generate(**inputs, forced_bos_token_id=tokenizer.convert_tokens_to_ids("eng_Latn"), max_new_tokens=400)
        out.append(tokenizer.batch_decode(tokens, skip_special_tokens=True)[0])
    return out


def main():
    parser = argparse.ArgumentParser(description="Sesotho test: language of each piece, Sesotho transcript, English translation.")
    parser.add_argument("wav", help="16 kHz mono WAV (convert with ffmpeg first)")
    parser.add_argument("--translate", action="store_true", help="also translate the Sesotho into English (NLLB)")
    parser.add_argument("--no-lid", action="store_true", help="skip language detection")
    args = parser.parse_args()

    dev = device()
    pieces = load(args.wav)
    print(f"{len(pieces)} pieces of up to 30 s, on {dev}")
    base = args.wav.rsplit(".", 1)[0]

    started = time.time()
    langs = None if args.no_lid else languages(pieces, dev)
    t_lid = time.time() - started

    started = time.time()
    texts = sesotho(pieces, dev)
    t_asr = time.time() - started

    with open(f"{base}-sesotho.txt", "w") as f:
        for i, text in enumerate(texts):
            lang = "" if langs is None else "  [" + ", ".join(f"{l} {p:.0%}" for l, p in langs[i]) + "]"
            f.write(f"--- piece {i + 1} ({i * 30 // 60}:{i * 30 % 60:02d}){lang}\n{text}\n\n")

    t_mt = 0.0
    if args.translate:
        started = time.time()
        translated = english(texts, dev)
        t_mt = time.time() - started
        with open(f"{base}-english.txt", "w") as f:
            for i, text in enumerate(translated):
                f.write(f"--- piece {i + 1} ({i * 30 // 60}:{i * 30 % 60:02d})\n{text}\n\n")

    print(f"Language ID {t_lid:.1f}s, Sesotho transcript {t_asr:.1f}s, translation {t_mt:.1f}s")
    print(f"Wrote {base}-sesotho.txt" + (f" and {base}-english.txt" if args.translate else ""))


if __name__ == "__main__":
    main()
