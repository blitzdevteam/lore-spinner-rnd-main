import { ChapterStatusEnum, GenderEnum, StoryRatingEnum, StoryStatusEnum } from '@/types/enum';

export interface EnumResource<T = string> {
    value: T;
    label: string;
}

export interface UserInterface {
    id: number;
    first_name: string | null;
    last_name: string | null;
    full_name: string | null;
    gender: GenderEnum | null;
    username: string | null;
    email: string;
    avatar: string;
    bio: string | null;
}

export interface CreatorInterface {
    id: number;
    first_name: string | null;
    last_name: string | null;
    full_name: string | null;
    username: string | null;
    avatar: string;
    bio: string | null;
    is_active: boolean;

    // Relations
    stories?: StoryInterface[];

    // Counts
    stories_count?: number;
}

export interface StoryInterface {
    id: number;
    title: string;
    slug: string;
    teaser: string | null;
    opening: string | null;
    status: EnumResource<StoryStatusEnum>;
    rating: EnumResource<StoryRatingEnum>;
    published_at: string | null;
    updated_at: string | null;
    cover: string;
    banner: string;
    outro_poster: string | null;

    // Relations
    category?: CategoryInterface;
    creator?: CreatorInterface;
    chapters?: ChapterInterface[];
    comments?: CommentInterface[];

    // Counts
    chapters_count?: number;
    comments_count?: number;

    is_bookmarked?: boolean;
}

export type ComingSoonStorySection = 'hero' | 'featured-worlds' | 'new-stories' | 'coming-soon';

export interface ComingSoonStoryInterface {
    id: number;
    title: string;
    slug: string | null;
    section: ComingSoonStorySection;
    section_label: string;
    teaser: string | null;
    category: string | null;
    rating: string | null;
    author: string | null;
    series_label: string | null;
    image_position: string | null;
    position: number;
    is_active: boolean;
    cover: string;
    updated_at: string | null;
}

export interface CategoryInterface {
    id: number;
    title: string;

    // Relations
    stories?: StoryInterface[];
}

export interface ChapterInterface {
    id: number;
    position: number;
    title: string;
    teaser: string | null;
    content: string | null;
    status: EnumResource<ChapterStatusEnum>;
    cover: string;

    // Relations
    story?: StoryInterface;
    events?: EventInterface[];

    // Counts
    events_count?: number;
}

export interface CommentInterface {
    id: number;
    content: string;
    created_at: string | null;

    // Author (polymorphic)
    author?: {
        id: number;
        full_name: string;
        username: string | null;
        avatar: string;
    };
}

export interface EventInterface {
    id: number;
    position: number;
    title: string;
    content: string | null;
    objectives: string | null;
    attributes: string[] | null;

    // Relations
    chapter?: ChapterInterface;
}

export interface PromptInterface {
    id: string;
    game_id: string;
    session_number: number | null;
    prompt: string | null;
    response: string;
    choices: PromptChoice[];
    created_at: string | null;
    updated_at: string | null;

    // Relations
    game?: GameInterface;
}

export interface VisibleSuggestionInterface {
    text: string;
    canonical_choice_id: string | null;
    canonical_path_label: 'A' | 'B' | 'C' | null;
}

export type PromptChoice = string | VisibleSuggestionInterface;

export interface GameplayUsageSummary {
    prompt_count: number;
    prompt_input_tokens: number;
    prompt_output_tokens: number;
    prompt_tokens: number;
    voice_count: number;
    voice_characters: number;
}

export interface GameInterface {
    id: string;
    story_id: number;
    user_id: number;
    current_session_number: number | null;
    current_session_complete: boolean;
    uses_playable_moments: boolean;
    total_sessions: number;
    model: string;
    created_at: string | null;
    updated_at: string | null;

    // Relations
    story?: StoryInterface;
    user?: UserInterface;
    prompts?: PromptInterface[];
    currentEvent?: EventInterface;

    // Counts
    prompts_count?: number;

    // Cold-open UX: story-native chat-bar placeholder for the player's very first move.
    // Null when D10 has not been run for this story (graceful degradation → empty bar).
    first_input_hint?: string | null;

    // Deferred turn processing (LLM runs afterResponse to avoid proxy 504s).
    turn_pending?: boolean;
    turn_error?: string | null;
    opening_pending?: boolean;
    opening_error?: string | null;
}
