import { reactive, watch } from 'vue';

export interface GameplaySettings {
    fontSize: number;
    fontColor: string;
    backgroundColor: string;
    autoplay: boolean;
    /** Empty string = use the story's recommended voice. */
    voiceId: string;
}

const STORAGE_KEY = 'gameplay-settings';

const defaults: GameplaySettings = {
    fontSize: 18,
    fontColor: '#e5e5e5',
    backgroundColor: '',
    autoplay: true,
    voiceId: '',
};

const fontColorPresets = [
    { label: 'Light', value: '#e5e5e5' },
    { label: 'Warm', value: '#fde68a' },
    { label: 'Cool', value: '#93fce7' },
    { label: 'Soft', value: '#c4b5fd' },
    { label: 'Rose', value: '#fda4af' },
    { label: 'White', value: '#ffffff' },
];

const backgroundPresets = [
    { label: 'None', value: '' },
    { label: 'Dark', value: '#0f0f0f' },
    { label: 'Charcoal', value: '#1a1a2e' },
    { label: 'Navy', value: '#0d1b2a' },
    { label: 'Forest', value: '#0a1f1a' },
    { label: 'Wine', value: '#1a0a0a' },
];

function load(): GameplaySettings {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            const parsed = JSON.parse(raw);
            return { ...defaults, ...parsed };
        }
    } catch {
        // ignore
    }
    return { ...defaults };
}

const settings = reactive<GameplaySettings>(load());

watch(settings, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
}, { deep: true });

function reset() {
    Object.assign(settings, { ...defaults });
}

export function useGameplaySettings() {
    return {
        settings,
        defaults,
        fontColorPresets,
        backgroundPresets,
        reset,
    };
}
