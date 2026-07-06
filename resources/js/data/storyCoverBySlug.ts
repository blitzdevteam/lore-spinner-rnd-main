import iLoveLucyCover from '@/assets/featured/i-love-lucy.png';
import theMatrixCover from '@/assets/featured/the-matrix.png';
import janeEyreCover from '@/assets/featured/janeEyre.png';
import frankensteinComingCover from '@/assets/commingSoon/frankstein-comming.png';
import drjComingCover from '@/assets/commingSoon/drj-comming.png';
import underseaComingCover from '@/assets/commingSoon/undersea-comming.JPG';
import pjPosterCover from '@/assets/commingSoon/pj-comming.JPG';
import romeoComingCover from '@/assets/commingSoon/romeo-comming.png';
import wastelandPosterCover from '@/assets/commingSoon/wasteland-comming.JPG';
import aliceCover from '@/assets/featured/alice.png';
import animaCover from '@/assets/featured/anima.png';
import draculaCover from '@/assets/featured/dracula.png';
import jekyllCover from '@/assets/featured/jekyll.png';
import nocturneCover from '@/assets/featured/nocturne.png';
import pridePrejudiceCover from '@/assets/featured/Pride-prejudice.png';
import redDeathCover from '@/assets/featured/redDeath.png';
import treasureCover from '@/assets/featured/treasure.png';
import sherlockCover from '@/assets/featured/sherlock.png';
import tellTaleCover from '@/assets/featured/Tell Tale portrait titled.png';
import wizardOzCover from '@/assets/featured/wizardoz.jpg';
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
    'the-tell-tale-heart': tellTaleCover,
    'i-love-lucy-job-switching': iLoveLucyCover,
    'the-matrix': theMatrixCover,
    wasteland: wastelandPosterCover,
    pjs: pjPosterCover,
    // Explore by Mood / mood library picks
    'jekyll-and-hyde': jekyllCover,
    'the-strange-case-of-dr-jekyll-and-mr-hyde': drjComingCover,
    '20000-leagues-under-the-sea': underseaComingCover,
    // Coming Soon / library portrait cards
    'jane-eyre': janeEyreCover,
    frankenstein: frankensteinComingCover,
    'dr-jekyll-and-mr-hyde': drjComingCover,
    leagues: underseaComingCover,
    'romeo-and-juliet': romeoComingCover,
};

export function resolveStoryCover(slug: string, cover?: string | null): string {
    const canonical = canonicalMoodStorySlug(slug);
    const override = STORY_COVER_BY_SLUG[slug] ?? STORY_COVER_BY_SLUG[canonical];
    if (override) return override;

    const trimmed = cover?.trim();
    if (trimmed) return trimmed;

    return '';
}
