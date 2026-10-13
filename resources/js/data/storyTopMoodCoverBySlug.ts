import leaguesTopMood from '@/assets/top-moods/20,000 leagues 16_9 untitled.webp';
import aliceTopMood from '@/assets/top-moods/Alice in wonderland 16_9 untitled.webp';
import animaTopMood from '@/assets/top-moods/Anima machina 16_9 untitled.webp';
import draculaTopMood from '@/assets/top-moods/Dracula 16_9 untitled.webp';
import frankensteinTopMood from '@/assets/top-moods/Frankenstein 16_9 untitled.webp';
import janeEyreTopMood from '@/assets/top-moods/Jane eyre 16_9 untitled.webp';
import jekyllTopMood from '@/assets/top-moods/Jeckell Hyde 16_9 untitled.webp';
import redDeathTopMood from '@/assets/top-moods/Masque red death 16_9 untitled.webp';
import nocturneTopMood from '@/assets/top-moods/Nocturne 16_9 untitled.webp';
import lucyHomeLandscape from '@/assets/newStories/lucy-landscape-banner.webp';
import ozHomeLandscape from '@/assets/newStories/Oz landscape titled.webp';
import marvelousOzCover from '@/assets/featured/the-marvelous-land-of-oz.webp';
import holyGrailCover from '@/assets/featured/the-quest-of-the-holy-grail.webp';
import senseAndSensibilityCover from '@/assets/featured/sense-and-sensibility.webp';
import blueCarbuncleCover from '@/assets/newStories/blue-carbuncle-wide.webp';
import emmaCover from '@/assets/newStories/emma-wide.webp';
import scandalBohemiaCover from '@/assets/newStories/scandal-in-bohemia-wide.webp';
import speckledBandCover from '@/assets/newStories/speckled-band-landscape.webp';
import tellTaleHomeLandscape from '@/assets/newStories/Tell Tale 5_3 landscape.webp';
import pjsTopMood from '@/assets/top-moods/PJS 16_9 untitled.webp';
import prideTopMood from '@/assets/top-moods/Pride prejudice 16_9 untitled.webp';
import romeoTopMood from '@/assets/top-moods/Romeo juliet 16_9 untitled.webp';
import treasureTopMood from '@/assets/top-moods/Treasure island 16_9 untitled.webp';
import momotaroFeatured from '@/assets/featured/momotaro.webp';
import wastelandTopMood from '@/assets/top-moods/Wasteland wide 16_9 untitled.webp';
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
    'the-adventure-of-the-speckled-band': speckledBandCover,
    'the-adventure-of-the-blue-carbuncle': blueCarbuncleCover,
    'a-scandal-in-bohemia': scandalBohemiaCover,
    'the-marvelous-land-of-oz': marvelousOzCover,
    'the-quest-of-the-holy-grail': holyGrailCover,
    'sense-and-sensibility': senseAndSensibilityCover,
    emma: emmaCover,
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
