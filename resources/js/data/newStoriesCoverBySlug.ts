import animaLandscape from '@/assets/top-moods/Anima machina 16_9 untitled.png';
import ozLandscape from '@/assets/newStories/Oz landscape titled.png';
import ozTopMood from '@/assets/top-moods/Oz 16_9 untitled.png';
import prideLandscape from '@/assets/newStories/Pride Landscape art only.png';
import prideTopMood from '@/assets/top-moods/Pride prejudice 16_9 untitled.png';
import sherlockLandscape from '@/assets/newStories/sherlock-new.png';
import sherlockTopMood from '@/assets/top-moods/Sherlock in the speckled band 16_9 untitled.png';
import tellTaleLandscape from '@/assets/newStories/Tell Tale 5_3 landscape.png';
import wastelandLandscape from '@/assets/top-moods/Wasteland wide 16_9 untitled.JPG';
import animaFeatured from '@/assets/featured/anima.png';
import draculaFeatured from '@/assets/featured/dracula.png';
import momotaroFeatured from '@/assets/featured/momotaro.png';
import nocturneFeatured from '@/assets/featured/nocturne.png';
import unwrittenPlaceholder from '@/assets/newStories/New stories 1- 2x.jpg';
import { resolveStoryTopMoodCover } from '@/data/storyTopMoodCoverBySlug';

/** Landscape art for the home “New Stories” slider — mirrors `SeedComingSoonStoriesCommand::newStoriesCoverPaths()`. */
export const NEW_STORIES_COVER_BY_SLUG: Record<string, string> = {
    'the-adventure-of-the-speckled-band': sherlockLandscape,
    'the-adventure-of-the-blue-carbuncle': sherlockTopMood,
    'a-scandal-in-bohemia': sherlockTopMood,
    'the-wonderful-wizard-of-oz': ozLandscape,
    'the-marvelous-land-of-oz': ozTopMood,
    'theseus-and-the-minotaur': draculaFeatured,
    'orpheus-and-eurydice': draculaFeatured,
    'havoc-in-heaven': draculaFeatured,
    'the-white-bone-demon': draculaFeatured,
    'pride-and-prejudice': prideLandscape,
    emma: prideTopMood,
    'anima-machina': animaLandscape,
    wasteland: wastelandLandscape,
    driftheart: animaFeatured,
    'dream-police': nocturneFeatured,
    'the-unwritten-ones': unwrittenPlaceholder,
    'momotaro-the-peach-boy': momotaroFeatured,
    'the-tell-tale-heart': tellTaleLandscape,
};

export function resolveNewStoryCover(slug: string, cover?: string | null): string {
    const trimmed = cover?.trim();
    if (trimmed) return trimmed;

    return NEW_STORIES_COVER_BY_SLUG[slug] ?? resolveStoryTopMoodCover(slug, cover);
}
