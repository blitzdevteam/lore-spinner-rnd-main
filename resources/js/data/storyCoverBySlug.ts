import iLoveLucyCover from '@/assets/featured/i-love-lucy.webp';
import theMatrixCover from '@/assets/featured/the-matrix.webp';
import janeEyreCover from '@/assets/featured/janeEyre.webp';
import frankensteinComingCover from '@/assets/commingSoon/frankstein-comming.webp';
import underseaComingCover from '@/assets/commingSoon/undersea-comming.webp';
import pjPosterCover from '@/assets/commingSoon/pj-comming.webp';
import romeoComingCover from '@/assets/commingSoon/romeo-comming.webp';
import wastelandPosterCover from '@/assets/commingSoon/wasteland-comming.webp';
import aliceCover from '@/assets/featured/alice.webp';
import animaCover from '@/assets/featured/anima.webp';
import draculaCover from '@/assets/featured/dracula.webp';
import jekyllCover from '@/assets/featured/jekyll.webp';
import nocturneCover from '@/assets/featured/nocturne.webp';
import pridePrejudiceCover from '@/assets/featured/Pride-prejudice.webp';
import redDeathCover from '@/assets/featured/redDeath.webp';
import treasureCover from '@/assets/featured/treasure.webp';
import sherlockCover from '@/assets/featured/sherlock-speckled-band.webp';
import tellTaleCover from '@/assets/featured/Tell Tale portrait titled.webp';
import wizardOzCover from '@/assets/featured/wizardoz.webp';
import marvelousOzCover from '@/assets/featured/the-marvelous-land-of-oz.webp';
import holyGrailCover from '@/assets/featured/the-quest-of-the-holy-grail.card.webp';
import senseAndSensibilityCover from '@/assets/featured/sense-and-sensibility.card.webp';
import momotaroCover from '@/assets/featured/momotaro.webp';
import { canonicalMoodStorySlug } from '@/data/moodStories';

/** Portrait / card covers aligned with home — override API media when present. */
export const STORY_COVER_BY_SLUG: Record<string, string> = {
    // Featured Worlds
    nocturne: nocturneCover,
    'the-masque-of-the-red-death': redDeathCover,
    'anima-machina': animaCover,
    'alice-in-wonderland': aliceCover,
    'alices-adventures-in-wonderland': aliceCover,
    dracula: draculaCover,
    'pride-and-prejudice': pridePrejudiceCover,
    'treasure-island': treasureCover,
    // New Stories
    'the-adventure-of-the-speckled-band': sherlockCover,
    'the-wonderful-wizard-of-oz': wizardOzCover,
    'the-marvelous-land-of-oz': marvelousOzCover,
    'the-tell-tale-heart': tellTaleCover,
    'i-love-lucy-job-switching': iLoveLucyCover,
    'the-matrix': theMatrixCover,
    wasteland: wastelandPosterCover,
    pjs: pjPosterCover,
    'momotaro-the-peach-boy': momotaroCover,
    // Explore by Mood / mood library picks
    'jekyll-and-hyde': jekyllCover,
    'the-strange-case-of-dr-jekyll-and-mr-hyde': jekyllCover,
    '20000-leagues-under-the-sea': underseaComingCover,
    // Coming Soon / library portrait cards
    'jane-eyre': janeEyreCover,
    frankenstein: frankensteinComingCover,
    'dr-jekyll-and-mr-hyde': jekyllCover,
    leagues: underseaComingCover,
    'romeo-and-juliet': romeoComingCover,
    'the-quest-of-the-holy-grail': holyGrailCover,
    'sense-and-sensibility': senseAndSensibilityCover,
};

export function resolveStoryCover(slug: string, cover?: string | null): string {
    const canonical = canonicalMoodStorySlug(slug);
    const override = STORY_COVER_BY_SLUG[slug] ?? STORY_COVER_BY_SLUG[canonical];
    if (override) return override;

    const trimmed = cover?.trim();
    if (trimmed) return trimmed;

    return '';
}
