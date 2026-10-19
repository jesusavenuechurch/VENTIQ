import { useEffect, useState } from 'react';

// Where each district is, to guess the nearest from the browser's location.
const CENTROIDS: Record<string, [number, number]> = {
    Maseru: [-29.31, 27.48], Leribe: [-28.87, 28.05], Mafeteng: [-29.82, 27.25],
    "Mohale's Hoek": [-30.15, 27.47], 'Butha-Buthe': [-28.75, 28.25],
    "Qacha's Nek": [-30.11, 28.68], Mokhotlong: [-29.29, 29.07],
    Quthing: [-30.40, 27.72], Berea: [-29.13, 27.73], 'Thaba-Tseka': [-29.52, 28.61],
};

function nearest(lat: number, lon: number): string | null {
    const toRad = (d: number) => (d * Math.PI) / 180;
    let best: string | null = null;
    let bestDist = Infinity;
    for (const [name, [dLat0, dLon0]] of Object.entries(CENTROIDS)) {
        const dLat = toRad(dLat0 - lat);
        const dLon = toRad(dLon0 - lon);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat)) * Math.cos(toRad(dLat0)) * Math.sin(dLon / 2) ** 2;
        const dist = 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        if (dist < bestDist) {
            bestDist = dist;
            best = name;
        }
    }
    return best;
}

/**
 * The district search ranks first: Maseru, or the nearest one if the
 * browser shares its location (denied or unavailable keeps Maseru, no nag).
 * Pages listening for "district-changed" (the home page) follow it.
 */
export function useDistrict(): [string, (d: string) => void] {
    const [district, setDistrict] = useState('Maseru');

    const choose = (d: string) => {
        setDistrict(d);
        window.dispatchEvent(new CustomEvent('district-changed', { detail: d }));
    };

    useEffect(() => {
        navigator.geolocation?.getCurrentPosition(
            (pos) => {
                const found = nearest(pos.coords.latitude, pos.coords.longitude);
                if (found) choose(found);
            },
            () => {},
        );
    }, []);

    return [district, choose];
}
