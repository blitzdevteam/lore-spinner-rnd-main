<?php

declare(strict_types=1);

it('resets the narrator pick to the recommended voice when the title changes', function (): void {
    $settings = file_get_contents(resource_path('js/composables/useGameplaySettings.ts'));
    $layout = file_get_contents(resource_path('js/layouts/GameplayLayout.vue'));
    $picker = file_get_contents(resource_path('js/components/GameplayVoicePicker.vue'));

    expect($settings)
        ->toContain('voiceStorySlug')
        ->toContain('function syncVoiceForStory')
        ->toContain("settings.voiceId = ''")
        ->and($layout)
        ->toContain('syncVoiceForStory')
        ->toContain('tts.invalidateCache()')
        ->and($picker)
        ->toContain("selectVoice('')")
        ->toContain('Story default');
});
