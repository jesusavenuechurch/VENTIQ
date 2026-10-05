// Generates the fixed set of avatars in public/images/avatars.
// Run with: node scripts/generate-avatars.mjs
//
// Style: "Big Smile" by Ashley Seo, CC BY 4.0
// (https://www.figma.com/community/file/881358461963645496), drawn with
// DiceBear (MIT). The credit is shown on the Team page.
//
// Only cheerful faces, skin tones weighted to brown, and the palette's
// soft tints as backgrounds. The files are committed, so neither the
// build nor the server needs this script.
import { createAvatar } from '@dicebear/core';
import { bigSmile } from '@dicebear/collection';
import { writeFileSync } from 'node:fs';

export const COUNT = 32;

const options = {
    skinColor: ['643d19', '8c5a2b', 'a47539', 'c99c62', 'e2ba87'],
    eyes: ['cheery', 'normal', 'starstruck', 'winking'],
    mouth: ['openedSmile', 'gapSmile', 'teethSmile', 'kawaii'],
    hair: ['shortHair', 'wavyBob', 'curlyBob', 'braids', 'shavedHead', 'bunHair', 'froBun', 'curlyShortHair', 'bangs'],
    hairColor: ['220f00', '3a1a00', '71472d'],
    accessories: ['glasses', 'sunglasses'],
    accessoriesProbability: 15,
    backgroundColor: ['e6f6ec', 'efeafd', 'fff1e6', 'e8eef6'],
    radius: 50,
};

for (let i = 0; i < COUNT; i++) {
    const svg = createAvatar(bigSmile, { ...options, seed: `ventiq-${i}` }).toString();
    writeFileSync(new URL(`../public/images/avatars/${i}.svg`, import.meta.url), svg);
}

console.log(`Wrote ${COUNT} avatars.`);
