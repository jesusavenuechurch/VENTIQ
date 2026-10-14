// Generates the fixed set of avatars in public/images/avatars.
// Run with: node scripts/generate-avatars.mjs
//
// VENTIQ's own people, drawn like the Mosotho man on the first-visit
// loading screen (resources/views/partials/lumela.blade.php): head and
// shoulders, a Basotho blanket with its bands, and a mokorotlo, a head
// wrap (tuku) or hair. Each avatar is a mix of skin tone, blanket colours
// and pattern, headwear, face and the odd extra, picked from a seed, so
// the set is the same every time. No dependencies; the files are
// committed, so neither the build nor the server needs this script.
import { writeFileSync } from 'node:fs';

export const COUNT = 32;

// Small seeded random numbers (mulberry32), so every run draws the same set.
const rng = (seed) => () => {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};
const pick = (r, list) => list[Math.floor(r() * list.length)];

const shade = (hex, f) => '#' + [1, 3, 5].map(i => Math.max(0, Math.min(255, Math.round(parseInt(hex.slice(i, i + 2), 16) * f)))
    .toString(16).padStart(2, '0')).join('');

const SKIN = ['#5C3A21', '#7A4A2A', '#8D5A36', '#A0693F', '#B57B4C'];
// Blanket: [body, bands, motif], in the colours Basotho blankets come in.
const BLANKETS = [
    ['#F07F22', '#132C4D', '#F6E7C8'],   // VENTIQ orange and navy
    ['#1D4069', '#F07F22', '#F6E7C8'],
    ['#8E1B2A', '#1C1C1C', '#E9C46A'],   // maroon
    ['#1E6B4F', '#F2C14E', '#F6E7C8'],   // green and gold
    ['#3E3A8C', '#E76F51', '#F6E7C8'],   // indigo
    ['#C2410C', '#3B1F0E', '#FCE7C8'],   // rust
    ['#0F766E', '#132C4D', '#F2C14E'],   // teal
    ['#5B2A86', '#F2C14E', '#F6E7C8'],   // purple
];
const MOTIFS = ['diamonds', 'triangles', 'dots', 'stripes'];
const WRAPS = ['#E63946', '#F2C14E', '#2A9D8F', '#F07F22', '#7B2CBF', '#1D4069'];
const HAIR = ['#1B120C', '#2A1A10', '#3A2416'];
const BACKGROUNDS = ['#E6F6EC', '#EFEAFD', '#FFF1E6', '#E8EEF6', '#FDF2D8'];
// Headwear spread evenly over the set.
const HEADWEAR = ['mokorotlo', 'wrap', 'short', 'afro', 'bun', 'mokorotlo', 'wrap', 'shaved'];

function motif(kind, y, colour) {
    const xs = [40, 70, 100, 130, 160];
    switch (kind) {
        case 'diamonds':  return xs.map(x => `<path d="M${x} ${y}l7 4.5-7 4.5-7-4.5Z" fill="${colour}"/>`).join('');
        case 'triangles': return xs.map(x => `<path d="M${x - 7} ${y + 9}l7-9 7 9Z" fill="${colour}"/>`).join('');
        case 'dots':      return xs.map(x => `<circle cx="${x}" cy="${y + 4.5}" r="2.6" fill="${colour}"/>`).join('');
        default:          return `<rect x="0" y="${y + 3.5}" width="200" height="2" fill="${colour}"/>`;
    }
}

function mokorotlo() {
    return `<g><ellipse cx="100" cy="97" rx="45" ry="8.5" fill="#B9862F"/>`
        + `<path d="M58 96 Q100 24 142 96 Q100 106 58 96 Z" fill="#E2B04F"/>`
        + `<path d="M67 85 Q100 77 133 85" stroke="#9C6E22" stroke-width="3.4" fill="none"/>`
        + `<path d="M74 73 Q100 67 126 73 M82 61 Q100 56 118 61 M90 49 Q100 46 110 49" stroke="#C4943A" stroke-width="1.6" fill="none"/>`
        + `<path d="M82 93 L95 48 M100 96 L100 44 M118 93 L105 48" stroke="#C4943A" stroke-width="1.2" fill="none" opacity=".7"/>`
        + `<rect x="97" y="31" width="6" height="9" rx="2" fill="#9C6E22"/>`
        + `<circle cx="100" cy="28" r="5" fill="none" stroke="#9C6E22" stroke-width="3"/></g>`;
}

function wrap(colour) {
    const dark = shade(colour, 0.78);
    return `<g><path d="M71 112 Q66 76 100 72 Q134 76 129 112 Q118 97 100 96 Q82 97 71 112 Z" fill="${colour}"/>`
        + `<path d="M76 99 Q100 88 124 99" stroke="${dark}" stroke-width="2.4" fill="none"/>`
        + `<ellipse cx="90" cy="70" rx="12" ry="7.5" transform="rotate(-28 90 70)" fill="${colour}"/>`
        + `<ellipse cx="110" cy="70" rx="12" ry="7.5" transform="rotate(28 110 70)" fill="${colour}"/>`
        + `<circle cx="100" cy="74" r="5.5" fill="${dark}"/></g>`;
}

function hair(style, colour) {
    switch (style) {
        case 'short': return `<path d="M74 113 Q70 85 100 84 Q130 85 126 113 Q121 97 100 96 Q79 97 74 113 Z" fill="${colour}"/>`;
        case 'afro':  return '';   // drawn behind the head
        case 'bun':   return `<path d="M74 113 Q70 85 100 84 Q130 85 126 113 Q121 97 100 96 Q79 97 74 113 Z" fill="${colour}"/>`
                           + `<circle cx="100" cy="79" r="12" fill="${colour}"/>`;
        default:      return '';   // shaved
    }
}

function face(r, skin) {
    const ink = '#1B1B1B', line = '#3B2414';
    const eyes = pick(r, ['dots', 'dots', 'happy', 'wink']);
    const mouth = pick(r, ['smile', 'smile', 'open', 'grin']);
    let out = '';
    if (eyes === 'happy') {
        out += `<path d="M85 116 q5 -5 10 0 M105 116 q5 -5 10 0" stroke="${ink}" stroke-width="2.6" fill="none" stroke-linecap="round"/>`;
    } else if (eyes === 'wink') {
        out += `<ellipse cx="90" cy="115" rx="2.6" ry="3.2" fill="${ink}"/><path d="M105 116 q5 -4 10 0" stroke="${ink}" stroke-width="2.6" fill="none" stroke-linecap="round"/>`;
    } else {
        out += `<ellipse cx="90" cy="115" rx="2.6" ry="3.2" fill="${ink}"/><ellipse cx="110" cy="115" rx="2.6" ry="3.2" fill="${ink}"/>`;
    }
    out += `<path d="M86 107 q4 -3 8 0 M106 107 q4 -3 8 0" stroke="${line}" stroke-width="2" fill="none" stroke-linecap="round"/>`;
    if (mouth === 'open') {
        out += `<path d="M89 128 Q100 142 111 128 Q100 132 89 128 Z" fill="#3B1A10"/>`;
    } else if (mouth === 'grin') {
        out += `<path d="M88 127 Q100 141 112 127 Z" fill="#FFFFFF" stroke="${line}" stroke-width="2" stroke-linejoin="round"/>`;
    } else {
        out += `<path d="M90 130 Q100 138 110 130" stroke="${line}" stroke-width="2.6" fill="none" stroke-linecap="round"/>`;
    }
    const blush = shade(skin, 1.3);
    out += `<ellipse cx="84" cy="125" rx="4" ry="2.5" fill="${blush}" opacity=".55"/><ellipse cx="116" cy="125" rx="4" ry="2.5" fill="${blush}" opacity=".55"/>`;
    return out;
}

function avatar(i) {
    const r = rng(9001 + i * 7919);
    const skin = pick(r, SKIN);
    const skinDark = shade(skin, 0.86);
    const [body, bands, mark] = BLANKETS[i % BLANKETS.length];
    const pattern = pick(r, MOTIFS);
    const headwear = HEADWEAR[i % HEADWEAR.length];
    const hairColour = pick(r, HAIR);
    const bg = BACKGROUNDS[i % BACKGROUNDS.length];
    const glasses = r() < 0.14 && headwear !== 'mokorotlo';
    const earrings = (headwear === 'wrap' || headwear === 'bun') && r() < 0.7;
    const beard = (headwear === 'mokorotlo' || headwear === 'short' || headwear === 'shaved') && r() < 0.3;

    const id = `b${i}`;
    let s = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="16 6 168 168" aria-hidden="true">`;
    s += `<defs><clipPath id="${id}"><path d="M14 210 Q18 154 100 146 Q182 154 186 210 Z"/></clipPath></defs>`;
    s += `<rect x="0" y="0" width="200" height="200" fill="${bg}"/>`;
    // Figure: moved up a little so the mokorotlo's knot stays in frame.
    // A mokorotlo needs the headroom; without one the person sits higher.
    s += `<g transform="translate(0 ${headwear === 'mokorotlo' ? -4 : headwear === 'afro' ? -12 : -18})">`;
    if (headwear === 'afro') {
        s += `<g fill="${hairColour}"><circle cx="100" cy="102" r="38"/><circle cx="70" cy="96" r="16"/><circle cx="130" cy="96" r="16"/><circle cx="84" cy="74" r="16"/><circle cx="116" cy="74" r="16"/><circle cx="100" cy="68" r="15"/></g>`;
    }
    // Blanket with its bands and pattern, and the fold over the shoulder.
    s += `<path d="M14 210 Q18 154 100 146 Q182 154 186 210 Z" fill="${body}"/>`;
    s += `<g clip-path="url(#${id})"><rect x="0" y="160" width="200" height="9" fill="${bands}"/><rect x="0" y="172" width="200" height="3" fill="${mark}"/><rect x="0" y="178" width="200" height="9" fill="${bands}"/>${motif(pattern, 160, mark)}</g>`;
    s += `<path d="M100 146 Q134 154 152 210" fill="none" stroke="${shade(body, 0.8)}" stroke-width="3" stroke-linecap="round"/>`;
    // Neck, ears, head.
    s += `<rect x="89" y="136" width="22" height="16" rx="7" fill="${skinDark}"/>`;
    s += `<ellipse cx="73.5" cy="120" rx="5" ry="7" fill="${skinDark}"/><ellipse cx="126.5" cy="120" rx="5" ry="7" fill="${skinDark}"/>`;
    if (earrings) s += `<circle cx="73" cy="130" r="3" fill="#E2B04F"/><circle cx="127" cy="130" r="3" fill="#E2B04F"/>`;
    s += `<ellipse cx="100" cy="117" rx="26" ry="30" fill="${skin}"/>`;
    if (beard) s += `<path d="M76 122 Q78 148 100 149 Q122 148 124 122 Q118 140 100 141 Q82 140 76 122 Z" fill="${hairColour}" opacity=".85"/>`;
    s += face(r, skin);
    if (glasses) s += `<g fill="none" stroke="#1B1B1B" stroke-width="2"><circle cx="90" cy="115" r="7.5"/><circle cx="110" cy="115" r="7.5"/><path d="M97.5 115h5"/></g>`;
    if (headwear === 'mokorotlo') s += mokorotlo();
    else if (headwear === 'wrap') s += wrap(pick(r, WRAPS));
    else s += hair(headwear, hairColour);
    s += `</g></svg>`;
    return s;
}

for (let i = 0; i < COUNT; i++) {
    writeFileSync(new URL(`../public/images/avatars/${i}.svg`, import.meta.url), avatar(i));
}

console.log(`Wrote ${COUNT} avatars.`);
