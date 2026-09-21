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
    subscription_usage?: SubscriptionUsageSummary | null;
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
    is_playable?: boolean;
    launch_world?: string | null;
}

export type MultiplayerLobbyMode = 'arbiter' | 'roulette';

export type MultiplayerLobbyStatus = 'waiting' | 'started' | 'paused' | 'closed' | 'completed' | 'cancelled';

export type MultiplayerLobbyMemberRole = 'host' | 'player';

export interface MultiplayerLobbySettingsInterface {
    custom_input_enabled: boolean;
    voice_over_enabled: boolean;
    power_ups_per_user: number;
    decision_window_seconds: number;
}

export interface MultiplayerLobbyPlayerInterface {
    id: number;
    full_name: string;
    username: string | null;
    avatar: string;
}

export interface MultiplayerLobbyMemberInterface {
    id: string;
    role: MultiplayerLobbyMemberRole;
    is_ready: boolean;
    joined_at: string | null;
    left_at: string | null;
    last_seen_at: string | null;
    score: number;
    turns_played: number;
    user: MultiplayerLobbyPlayerInterface;
}

export interface MultiplayerLobbyChatMessageInterface {
    id: string;
    body: string;
    created_at: string | null;
    user: MultiplayerLobbyPlayerInterface;
}

export interface MultiplayerLobbyInterface {
    id: string;
    room_code: string;
    invite_url?: string;
    mode: MultiplayerLobbyMode;
    status: MultiplayerLobbyStatus;
    max_players: number;
    settings: MultiplayerLobbySettingsInterface;
    created_at: string | null;
    story: StoryInterface;
    host: MultiplayerLobbyPlayerInterface;
    members: MultiplayerLobbyMemberInterface[];
    is_host: boolean;
}

export interface MultiplayerLobbyCardInterface {
    id: string;
    room_code: string;
    mode: MultiplayerLobbyMode;
    max_players: number;
    players_count: number;
    story: Pick<StoryInterface, 'title' | 'slug' | 'cover'>;
    is_host: boolean;
}

export type MultiplayerArbiterTurnStatus =
    | 'opening'
    | 'collecting_proposals'
    | 'awaiting_arbiter'
    | 'awaiting_roulette_choice'
    | 'narrating'
    | 'session_complete'
    | null;

export interface MultiplayerLobbyProposalInterface {
    id: string;
    member_id: string;
    text: string | null;
    choice_index: number | null;
    is_selected: boolean;
    selected_at: string | null;
}

export interface MultiplayerGeneratedChoiceInterface {
    index: number;
    text: string;
    canonical_choice_id: string | null;
    canonical_path_label: 'A' | 'B' | 'C' | null;
}

export interface MultiplayerArbiterStateInterface {
    mode: MultiplayerLobbyMode;
    turn_status: MultiplayerArbiterTurnStatus;
    source_prompt_id: string | null;
    current_arbiter_member_id: string | null;
    current_chooser_member_id: string | null;
    decision_deadline_at: string | null;
    decision_remaining_seconds: number | null;
    decision_window_seconds: number;
    is_current_user_arbiter: boolean;
    is_current_user_chooser: boolean;
    awaiting_member_ids: string[];
    generated_choices: MultiplayerGeneratedChoiceInterface[];
    proposals: MultiplayerLobbyProposalInterface[];
    last_selected_proposal: MultiplayerLobbyProposalInterface | null;
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

export interface WorldInterface {
    id: number;
    title: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    tags: string[];
    position: number;
    is_active: boolean;
    background: string;
    stories?: StoryInterface[];
    stories_count?: number;
    updated_at: string | null;
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

/** A playable episode — one adaptation session, not an extracted book chapter. */
export interface StoryEpisodeInterface {
    number: number;
    teaser: string | null;
    cover: string;
    is_playable: boolean;
    is_completed: boolean;
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

/** The game fields the play page needs after a turn commits — see GameNarrationStreamed::gamePayload(). */
export interface GameTurnStateInterface {
    current_session_number: number | null;
    current_session_complete: boolean;
    uses_playable_moments: boolean;
}

/** Broadcast on `private-game.{id}` as `.game.narration.streamed` while a turn runs after the request returned. */
export interface GameNarrationSignal {
    game_id: string;
    source_prompt_id: string;
    phase: 'ready' | 'delta' | 'done' | 'error';
    text?: string;
    source_prompt_value?: string;
    prompt?: PromptInterface;
    game?: GameTurnStateInterface;
    message?: string;
}

/** POST /user/games/{game}/prompt with Accept: application/json. */
export interface TurnAcceptedResponse {
    accepted: true;
    game_id: string;
    source_prompt_id: string;
    source_prompt_value: string;
    channel: string;
}

export interface TurnRejectedResponse {
    accepted: false;
    reason: 'in_progress' | 'out_of_turn' | 'already_answered' | 'invalid';
    message: string;
    source_prompt_id?: string;
    source_prompt_value?: string;
    prompt?: PromptInterface;
    game?: GameTurnStateInterface;
}

/** GET /user/games/{game}/turn-status?source_prompt_id= */
export interface TurnStatusResponse {
    pending: boolean;
    pending_source_prompt_id: string | null;
    error: string | null;
    source_prompt_id: string | null;
    source_prompt_value: string | null;
    prompt: PromptInterface | null;
    game: GameTurnStateInterface;
}

export interface GameplayUsageTotals {
    turns: number;
    voice_duration_ms: number;
}

export interface GameplayUsageSummary {
    game: GameplayUsageTotals;
    total: GameplayUsageTotals;
}

export interface SubscriptionUsageSummary {
    plan: {
        name: string;
    };
    actions: {
        limit: number;
        used: number;
        remaining: number;
    };
    voice: {
        limit_minutes: number;
        used_milliseconds: number;
        remaining_milliseconds: number;
    };
    period_ends_at: string | null;
    is_complimentary?: boolean;
}

export interface CompletionIdentity {
    identity: string;
    style_line: string | null;
}

export interface CompletionWhatWaits {
    next_opens: string;
    unresolved_promise: string | null;
}

export interface CompletionReceipt {
    mode: 'session' | 'story';
    story_title: string;
    session_number: number;
    final_line?: string;
    world_remembers?: string[];
    becoming?: CompletionIdentity;
    commitment?: string;
    what_waits?: CompletionWhatWaits;
    what_settled?: string[];
    record?: string[];
    became?: CompletionIdentity;
    leaves_behind?: string;
    share_line?: string;
}

export interface AchievementCard {
    id: number;
    mode: 'session' | 'story';
    session_number: number;
    label: string;
    preview: string | null;
    earned_at: string | null;
    receipt: CompletionReceipt;
}

export interface AchievementStory {
    id: number;
    title: string;
    slug: string;
    cover: string;
    total_sessions: number;
    episode_count: number;
    story_complete: boolean;
    episodes: AchievementCard[];
    story_card: AchievementCard | null;
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
    completion_receipt?: CompletionReceipt | null;
}
