import leaguesTopMood from '@/assets/top-moods/20,000 leagues 16_9 untitled.JPG';
import aliceTopMood from '@/assets/top-moods/Alice in wonderland 16_9 untitled.png';
import animaTopMood from '@/assets/top-moods/Anima machina 16_9 untitled.png';
import draculaTopMood from '@/assets/top-moods/Dracula 16_9 untitled.JPG';
import frankensteinTopMood from '@/assets/top-moods/Frankenstein 16_9 untitled.png';
import janeEyreTopMood from '@/assets/top-moods/Jane eyre 16_9 untitled.png';
import jekyllTopMood from '@/assets/top-moods/Jeckell Hyde 16_9 untitled.png';
import redDeathTopMood from '@/assets/top-moods/Masque red death 16_9 untitled.png';
import nocturneTopMood from '@/assets/top-moods/Nocturne 16_9 untitled.png';
import lucyHomeLandscape from '@/assets/newStories/lucy-landscape-banner.jpeg';
import ozHomeLandscape from '@/assets/newStories/Oz landscape titled.png';
import sherlockHomeLandscape from '@/assets/newStories/sherlock-new.png';
import tellTaleHomeLandscape from '@/assets/newStories/Tell Tale 5_3 landscape.png';
import pjsTopMood from '@/assets/top-moods/PJS 16_9 untitled.JPG';
import prideTopMood from '@/assets/top-moods/Pride prejudice 16_9 untitled.png';
import romeoTopMood from '@/assets/top-moods/Romeo juliet 16_9 untitled.png';
import treasureTopMood from '@/assets/top-moods/Treasure island 16_9 untitled.png';
import momotaroFeatured from '@/assets/featured/momotaro.png';
import wastelandTopMood from '@/assets/top-moods/Wasteland wide 16_9 untitled.JPG';
import { resolveStoryCover } from '@/data/storyCoverBySlug';

/** 16:9 landscape art for mood-page “Top … Picks” banner cards. */
export const STORY_TOP_MOOD_COVER_BY_SLUG: Record<string, string> = {
    'alice-in-wonderland': aliceTopMood,
    'alices-adventures-in-wonderland': aliceTopMood,
    'anima-machina': animaTopMood,
    dracula: draculaTopMood,
    frankenstein: frankensteinTopMood,
    'i-love-lucy-job-switching': lucyHomeLandscape,
    'jane-eyre': janeEyreTopMood,
    'jekyll-and-hyde': jekyllTopMood,
    'dr-jekyll-and-mr-hyde': jekyllTopMood,
    '20000-leagues-under-the-sea': leaguesTopMood,
    leagues: leaguesTopMood,
    nocturne: nocturneTopMood,
    pjs: pjsTopMood,
    'pride-and-prejudice': prideTopMood,
    'romeo-and-juliet': romeoTopMood,
    'the-adventure-of-the-speckled-band': sherlockHomeLandscape,
    'the-adventure-of-the-blue-carbuncle': sherlockHomeLandscape,
    'a-scandal-in-bohemia': sherlockHomeLandscape,
    'the-marvelous-land-of-oz': ozHomeLandscape,
    emma: prideTopMood,
    'momotaro-the-peach-boy': momotaroFeatured,
    'the-unwritten-ones': tellTaleHomeLandscape,
    'the-masque-of-the-red-death': redDeathTopMood,
    'the-tell-tale-heart': tellTaleHomeLandscape,
    'the-wonderful-wizard-of-oz': ozHomeLandscape,
    'treasure-island': treasureTopMood,
    wasteland: wastelandTopMood,
};

export function resolveStoryTopMoodCover(slug: string, cover?: string | null): string {
    const topMood = STORY_TOP_MOOD_COVER_BY_SLUG[slug];
    if (topMood) {
        return topMood;
    }

    return resolveStoryCover(slug, cover);
}
