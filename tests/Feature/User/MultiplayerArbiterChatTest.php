<?php

declare(strict_types=1);

it('rotates the arbiter chat send arrow upward to match the design', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($template)
        ->toContain('aria-label="Send message"')
        ->toContain('<img :src="sendIcon" alt="" class="rotate-90" aria-hidden="true" />');
});

it('keeps arbiter rail tabs close to the top of the display', function () {
    $styles = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($styles)
        ->toContain("padding-top: 1.5625rem;")
        ->not->toContain('padding-top: 7.1875rem;');
});

it('pins the arbiter side rails while the story column scrolls', function () {
    $styles = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($styles)
        ->toContain('height: 100svh;')
        ->toContain('overflow-y: auto;')
        ->toContain('ref="storyColumn"');
});

it('toggles the arbiter players and chat panels from their rail buttons', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($template)
        ->toContain('const isPlayersPanelOpen = ref(true)')
        ->toContain('const isChatPanelOpen = ref(true)')
        ->toContain('@click="togglePlayersPanel"')
        ->toContain('@click="toggleChatPanel"')
        ->toContain('v-show="isPlayersPanelOpen"')
        ->toContain('v-show="isChatPanelOpen"')
        ->toContain(':aria-expanded="isPlayersPanelOpen"')
        ->toContain(':aria-expanded="isChatPanelOpen"');
});

it('shares proposal typing state through the multiplayer presence channel', function () {
    $arbiterTemplate = file_get_contents(resource_path('js/pages/Arbiter.vue'));
    $inputTemplate = file_get_contents(resource_path('js/components/GameplayInput.vue'));

    expect($arbiterTemplate)
        ->toContain("const typingWhisperEvent = 'multiplayer.arbiter.typing'")
        ->toContain('usePresenceChannel(lobbyChannelName)')
        ->toContain('listenForWhisper(typingWhisperEvent, handleTypingWhisper)')
        ->toContain('whisper(typingWhisperEvent, { is_typing: isTyping })')
        ->toContain('@typing="handleProposalTyping"')
        ->toContain('typing-label--active');

    expect($inputTemplate)
        ->toContain('typing: [isTyping: boolean];')
        ->toContain("emit('typing', text.trim().length > 0)");
});
