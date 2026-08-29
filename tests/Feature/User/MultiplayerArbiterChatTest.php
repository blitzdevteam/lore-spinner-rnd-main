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

it('uses header controls and bottom sheets for arbiter panels on mobile', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($template)
        ->toContain("import GameplayBottomSheet from '@/components/GameplayBottomSheet.vue';")
        ->toContain('const isPlayersPanelOpen = ref(true)')
        ->toContain('const isChatPanelOpen = ref(true)')
        ->toContain("const activeMobilePanel = ref<MobilePanel>(null)")
        ->toContain("const MOBILE_MQ = '(max-width: 1120px)'")
        ->toContain('@click="togglePlayersPanel"')
        ->toContain('@click="toggleChatPanel"')
        ->toContain('v-show="isPlayersPanelOpen"')
        ->toContain('v-show="isChatPanelOpen"')
        ->toContain(':aria-expanded="isPlayersPanelOpen"')
        ->toContain(':aria-expanded="isChatPanelOpen"')
        ->toContain('class="arbiter-mobile-panel-controls"')
        ->toContain('aria-controls="arbiter-players-drawer"')
        ->toContain('aria-controls="arbiter-chat-drawer"')
        ->toContain(':open="isMobile && activeMobilePanel === \'players\'"')
        ->toContain(':open="isMobile && activeMobilePanel === \'chat\'"')
        ->toContain('class="arbiter-mobile-panel arbiter-mobile-players-panel"')
        ->toContain('class="arbiter-mobile-panel arbiter-mobile-chat-panel"')
        ->toContain('@media (max-width: 1120px)');
});

it('shares proposal typing state through the multiplayer presence channel', function () {
    $arbiterTemplate = file_get_contents(resource_path('js/pages/Arbiter.vue'));
    $inputTemplate = file_get_contents(resource_path('js/components/GameplayInput.vue'));

    expect($arbiterTemplate)
        ->toContain("const typingWhisperEvent = 'multiplayer.arbiter.typing'")
        ->toContain('usePresenceChannel(lobbyChannelName)')
        ->toContain('listenForWhisper(typingWhisperEvent, handleTypingWhisper)')
        ->toContain('whisper(typingWhisperEvent, {')
        ->toContain('is_typing: isTyping,')
        ->toContain('user_id: member.user.id,')
        ->toContain('@typing="handleProposalTyping"')
        ->toContain('typing-label--active');

    expect($inputTemplate)
        ->toContain('typing: [isTyping: boolean];')
        ->toContain("emit('typing', text.trim().length > 0)");
});
