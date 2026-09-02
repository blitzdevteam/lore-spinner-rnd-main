import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export interface TtsVoiceOption {
    id: string;
    label: string;
    description: string;
    gender: 'male' | 'female';
}

export function useTtsVoices() {
    const page = usePage<{ ttsVoices?: TtsVoiceOption[]; recommendedVoiceId?: string }>();

    const voices = computed(() =>
        (page.props.ttsVoices ?? []).map((voice) => ({
            ...voice,
            gender: voice.gender === 'female' ? 'female' as const : 'male' as const,
        })),
    );
    const recommendedVoiceId = computed(() => page.props.recommendedVoiceId ?? '');

    function recommendedLabel(): string {
        const match = voices.value.find((voice) => voice.id === recommendedVoiceId.value);
        return match?.label ?? 'Recommended';
    }

    return {
        voices,
        recommendedVoiceId,
        recommendedLabel,
    };
}
