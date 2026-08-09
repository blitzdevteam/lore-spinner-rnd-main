import heartImg from '@/assets/mood/Heart.svg';
import mountainsImg from '@/assets/mood/Mountains.svg';
import eyeImg from '@/assets/mood/Eye.svg';
import swordImg from '@/assets/mood/Sword.svg';
import spiralImg from '@/assets/mood/Spiral.svg';
import { MOOD_BANNER_CONFIGS, type MoodId } from '@/data/moodBanners';

export interface MoodPhotoCardConfig {
    id: MoodId;
    label: string;
    banner: string;
    accent: string;
    badgeBg: string;
    icon: string;
    iconClass?: string;
}

/** Figma 5412:903 — background photo + bottom-left icon badge + label. Shared by ExploreByMood and MoodSelector. */
export const MOOD_PHOTO_CARD_CONFIGS: MoodPhotoCardConfig[] = [
    {
        id: 'heartfelt',
        label: 'Heartfelt',
        banner: MOOD_BANNER_CONFIGS.heartfelt.banner,
        accent: '#FF3A3A',
        badgeBg: 'rgba(201, 52, 52, 0.15)',
        icon: heartImg,
    },
    {
        id: 'adventurous',
        label: 'Adventurous',
        banner: MOOD_BANNER_CONFIGS.adventurous.banner,
        accent: '#ffbe58',
        badgeBg: 'rgba(255, 190, 88, 0.12)',
        icon: mountainsImg,
    },
    {
        id: 'mysterious',
        label: 'Mysterious',
        banner: MOOD_BANNER_CONFIGS.mysterious.banner,
        accent: '#62e8db',
        badgeBg: 'rgba(98, 232, 219, 0.12)',
        icon: eyeImg,
    },
    {
        id: 'epic',
        label: 'Epic',
        banner: MOOD_BANNER_CONFIGS.epic.banner,
        accent: '#58d9a1',
        badgeBg: 'rgba(88, 217, 161, 0.12)',
        icon: swordImg,
        iconClass: '-rotate-45',
    },
    {
        id: 'whimsical',
        label: 'Whimsical',
        banner: MOOD_BANNER_CONFIGS.whimsical.banner,
        accent: '#D789FF',
        badgeBg: 'rgba(169, 121, 194, 0.15)',
        icon: spiralImg,
    },
];

export const MOOD_PHOTO_CARD_CONFIG_BY_ID = Object.fromEntries(
    MOOD_PHOTO_CARD_CONFIGS.map((config) => [config.id, config]),
) as Record<MoodId, MoodPhotoCardConfig>;
