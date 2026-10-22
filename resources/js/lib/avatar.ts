// The same illustrated person as App\Support\Avatar picks: crc32 of the
// lower-cased, trimmed seed, out of the 32 in public/images/avatars.
const TABLE = (() => {
    const t = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        t[n] = c >>> 0;
    }
    return t;
})();

function crc32(text: string): number {
    const bytes = new TextEncoder().encode(text);
    let crc = 0xffffffff;
    for (const b of bytes) crc = TABLE[(crc ^ b) & 0xff] ^ (crc >>> 8);
    return (crc ^ 0xffffffff) >>> 0;
}

export function avatarUrl(seed: string | null | undefined): string {
    return `/images/avatars/${crc32((seed ?? '').trim().toLowerCase()) % 32}.svg`;
}
