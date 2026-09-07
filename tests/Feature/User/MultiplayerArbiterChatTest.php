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
        ->toContain('padding-top: 1.5625rem;')
        ->not->toContain('padding-top: 7.1875rem;');
});

it('pins the arbiter side rails while the story column scrolls', function () {
    $styles = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($styles)
        ->toContain('height: 100svh;')
        ->toContain('overflow-y: auto;')
        ->toContain('ref="storyColumn"');
});

it('keeps the arbiter proposal input dock within the mobile viewport', function () {
    $styles = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($styles)
        ->toContain('    .arbiter-proposal-dock {')
        ->toContain('        right: 0;')
        ->toContain('        bottom: 0;')
        ->toContain('        left: 0;')
        ->toContain('        width: auto;')
        ->toContain('        max-width: none;')
        ->toContain('        box-sizing: border-box;')
        ->toContain('        padding: 1rem;')
        ->toContain('        padding-bottom: max(1rem, env(safe-area-inset-bottom));')
        ->toContain('        transform: none;');
});

it('places responsive narration controls in the arbiter proposal dock', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));
    $styles = $template;

    expect($template)
        ->toContain('v-if="voiceOverEnabled && tts.isActive.value && !isMobile" class="arbiter-media-player"')
        ->toContain('v-if="voiceOverEnabled && tts.isActive.value && isMobile" class="arbiter-proposal-media-player"');

    expect($styles)
        ->toContain('gap: 0.75rem;')
        ->toContain('.arbiter-proposal-media-player {')
        ->toContain('width: min(100%, 38.5rem);')
        ->toContain('.arbiter-proposal-media-player :deep(.player-bar) {');
});

it('uses the lobby voice-over and custom-input settings in roulette gameplay', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));
    $chatCard = file_get_contents(resource_path('js/components/GameplayChatCard.vue'));

    expect($template)
        ->toContain("const voiceOverEnabled = computed(() => isRouletteMode.value && props.lobby.settings.voice_over_enabled)")
        ->toContain('const shouldShowCustomInput = computed(() => !isRouletteMode.value || customInputEnabled.value)')
        ->toContain(':voice-over-enabled="voiceOverEnabled"');

    expect($chatCard)
        ->toContain('voiceOverEnabled?: boolean;')
        ->toContain('const voiceOverEnabled = computed(() => props.voiceOverEnabled ?? true);')
        ->toContain('v-if="voiceOverEnabled"');
});

it('uses header controls and bottom sheets for arbiter panels on mobile', function () {
    $template = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($template)
        ->toContain("import GameplayBottomSheet from '@/components/GameplayBottomSheet.vue';")
        ->toContain('const isPlayersPanelOpen = ref(true)')
        ->toContain('const isChatPanelOpen = ref(true)')
        ->toContain('const activeMobilePanel = ref<MobilePanel>(null)')
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

it('uses dark liquid glass for gameplay navbar controls', function (): void {
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($styles)
        ->toContain('/* Header controls — dark liquid glass */')
        ->toContain('linear-gradient(180deg, rgba(66, 66, 66, 0.48)')
        ->toContain('backdrop-filter: blur(6px) saturate(115%);')
        ->toContain('border: 1px solid rgba(255, 255, 255, 0.12);')
        ->toContain('inset 0 1px 0 rgba(255, 255, 255, 0.18)')
        ->not->toContain('background: #e6e6e6;');
});

it('centers the players and chat content using the figma dimensions', function (): void {
    $arbiter = file_get_contents(resource_path('js/pages/Arbiter.vue'));

    expect($arbiter)
        ->toContain('<img :src="playersIcon" alt="" class="size-[18px]"')
        ->toContain('<img :src="chatIcon" alt="" class="size-[18px]"')
        ->toContain('height: 2.1875rem !important;')
        ->toContain('padding: 0 0.6875rem !important;')
        ->toContain('font-size: 0.875rem;')
        ->toContain('justify-content: center;')
        ->toContain('gap: 0.375rem;');
});
