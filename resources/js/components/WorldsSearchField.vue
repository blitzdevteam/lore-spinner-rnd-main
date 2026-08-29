<script setup lang="ts">
import searchIcon from '@/assets/worlds/search.svg';

const model = defineModel<string>({ default: '' });
</script>

<template>
    <label class="worlds-search">
        <span class="sr-only">Search worlds, stories, moods, and creators</span>
        <span class="worlds-search__icon" aria-hidden="true">
            <img :src="searchIcon" alt="" width="18" height="18" class="size-full" />
        </span>
        <input
            v-model="model"
            type="search"
            class="worlds-search__input"
            placeholder="Search worlds, stories, moods, creators ..."
            autocomplete="off"
            enterkeyhint="search"
        />
    </label>
</template>

<style scoped>
.worlds-search {
    display: flex;
    height: 2.8125rem;
    width: 100%;
    min-width: 0;
    align-items: center;
    gap: 0.375rem;
    border-radius: 0.5rem;
    border: 0.5px solid #373737;
    background: #000;
    padding: 0.625rem 0.625rem 0.625rem 0.5rem;
    transition: border-color 150ms ease;
}

.worlds-search:focus-within {
    border-color: var(--color-primary, #59c1ca);
}

.worlds-search__icon {
    display: flex;
    width: 1.125rem;
    height: 1.125rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: clip;
}

.worlds-search__input {
    min-width: 0;
    flex: 1;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 2.0625rem;
    color: #fff;
    outline: none;
    box-shadow: none;
}

.worlds-search__input::placeholder {
    color: #373737;
}

.worlds-search__input::-webkit-search-decoration,
.worlds-search__input::-webkit-search-cancel-button {
    appearance: none;
}
</style>
