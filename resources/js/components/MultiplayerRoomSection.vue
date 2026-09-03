<script setup lang="ts">
import LibraryMultiplayerCard from '@/components/LibraryMultiplayerCard.vue';
import type { MultiplayerLobbyCardInterface } from '@/types';

defineProps<{
    rooms: MultiplayerLobbyCardInterface[];
}>();
</script>

<template>
    <div v-if="rooms.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <LibraryMultiplayerCard v-for="room in rooms" :key="room.id" :room="room" />
    </div>

    <div
        v-else
        class="rounded-lg border border-dashed border-[#373737] bg-[#0f0f0f] px-6 py-10 text-center text-sm text-[#7e7e7e]"
    >
        No multiplayer rooms are waiting for players right now.
    </div>
</template>
