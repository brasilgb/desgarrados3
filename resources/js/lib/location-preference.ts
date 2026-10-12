export type MunicipalityPreference = { code: string; state: string };

const preferenceKey = 'desgarrados:location:municipality';
const legacyKey = 'desgarrados:rural-calendar:municipality';
const choiceKey = 'desgarrados:location:choice';

export function getLocationChoice(): 'municipality' | 'declined' | null {
    try {
        const value = localStorage.getItem(choiceKey);
        return value === 'municipality' || value === 'declined' ? value : null;
    } catch { return null; }
}

export function saveLocationChoice(choice: 'municipality' | 'declined'): void {
    try { localStorage.setItem(choiceKey, choice); } catch { /* Keep the portal usable when storage is blocked. */ }
}

export function getMunicipalityPreference(): MunicipalityPreference | null {
    try {
        const stored = localStorage.getItem(preferenceKey) ?? localStorage.getItem(legacyKey);
        const value = JSON.parse(stored ?? 'null') as Partial<MunicipalityPreference> | null;
        if (value && /^\d{7}$/.test(value.code ?? '') && /^[A-Z]{2}$/.test(value.state ?? '')) {
            return { code: value.code!, state: value.state! };
        }
    } catch { /* Storage can be disabled by the browser. */ }
    return null;
}

export function saveMunicipalityPreference(value: MunicipalityPreference): void {
    try { localStorage.setItem(preferenceKey, JSON.stringify(value)); } catch { /* Keep navigation usable without storage. */ }
}

export function clearMunicipalityPreference(): void {
    try {
        localStorage.removeItem(preferenceKey);
        localStorage.removeItem(legacyKey);
    } catch { /* Keep navigation usable without storage. */ }
}
