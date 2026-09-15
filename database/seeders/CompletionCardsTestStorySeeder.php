<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Ai\Adaptation\RuntimeNarratorPromptValidator;
use App\Ai\Adaptation\RuntimeNarratorTemplateBuilder;
use App\Enums\Adaptation\AdaptationStatusEnum;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use App\Enums\BoilerStatus;
use App\Enums\Chapter\ChapterStatusEnum;
use App\Enums\Story\StoryRatingEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Creator;
use App\Models\Event;
use App\Models\PlayableMoment;
use App\Models\PlayableSessionCut;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Models\StoryAdaptation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Seed the internal Completion Card QA story.
 *
 * Usage: vendor/bin/sail artisan db:seed --class=CompletionCardsTestStorySeeder --force
 */
final class CompletionCardsTestStorySeeder extends Seeder
{
    public const string SLUG = 'test-the-last-signal';

    public const string TITLE = '[TEST] The Last Signal';

    public function run(): void
    {
        $scriptPath = database_path('stories/The Last Signal_script.txt');

        if (! File::exists($scriptPath)) {
            $this->command?->error('Script not found: The Last Signal_script.txt');

            return;
        }

        $creator = $this->ensureCreator();
        $category = Category::query()->firstOrCreate(['title' => 'Science Fiction']);

        $story = Story::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'category_id' => $category->id,
                'creator_id' => $creator->id,
                'title' => self::TITLE,
                'teaser' => 'An abandoned station is still receiving one last transmission. What you do with it will not stay inside the building.',
                'opening' => '<p>The station is already listening when you arrive. One last signal keeps arriving, and nobody is left to answer it.</p>',
                'status' => StoryStatusEnum::PUBLISHED,
                'rating' => StoryRatingEnum::TEEN,
                'published_at' => now(),
                'system_prompt' => [
                    'character_name' => 'you',
                ],
            ],
        );

        $this->replaceScript($story, $scriptPath);
        $this->replaceStoryBody($story);
        $this->seedAdaptation($story);
        $this->assembleRuntimePrompts($story);

        $this->command?->info('Seeded '.self::TITLE.' ('.self::SLUG.').');
        $this->command?->info('Play it from the library, or: vendor/bin/sail artisan db:seed --class=CompletionCardsTestStorySeeder --force');
    }

    private function ensureCreator(): Creator
    {
        $creator = null;

        Creator::withoutEvents(function () use (&$creator): void {
            $creator = Creator::query()->firstOrCreate(
                ['email' => 'qa@lorespinner.com'],
                [
                    'first_name' => 'LoreSpinner',
                    'last_name' => 'QA',
                    'username' => 'lorespinnerqa',
                    'password' => 'password',
                    'bio' => 'Internal stories used to verify LoreSpinner gameplay systems.',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        });

        if (! $creator instanceof Creator) {
            throw new RuntimeException('Could not create the LoreSpinner QA creator.');
        }

        return $creator;
    }

    private function replaceScript(Story $story, string $scriptPath): void
    {
        $story->clearMediaCollection('script');
        $story->addMedia($scriptPath)
            ->preservingOriginal()
            ->toMediaCollection('script');
    }

    private function replaceStoryBody(Story $story): void
    {
        $story->adaptation?->delete();

        foreach ($story->chapters as $chapter) {
            $chapter->events()->delete();
        }

        $story->chapters()->delete();

        $station = Chapter::query()->create([
            'story_id' => $story->id,
            'position' => 1,
            'title' => 'The Station',
            'teaser' => 'An empty dish is still receiving.',
            'content' => 'The abandoned communication station still holds a damaged transmitter and one repeating signal.',
            'status' => ChapterStatusEnum::READY_TO_PLAY,
        ]);

        $beyond = Chapter::query()->create([
            'story_id' => $story->id,
            'position' => 2,
            'title' => 'Beyond the Maps',
            'teaser' => 'The commitment leaves the building.',
            'content' => 'The player follows the unresolved choice to the place the maps deny.',
            'status' => ChapterStatusEnum::READY_TO_PLAY,
        ]);

        $frequency = Chapter::query()->create([
            'story_id' => $story->id,
            'position' => 3,
            'title' => 'The Last Frequency',
            'teaser' => 'One room remains.',
            'content' => 'The remaining message is already in the last room, waiting for a final authorship.',
            'status' => ChapterStatusEnum::READY_TO_PLAY,
        ]);

        Event::query()->create([
            'chapter_id' => $station->id,
            'position' => 1,
            'title' => 'Abandoned station',
            'content' => 'The access road ends at an unlatched fence. The communication station sits dark on the salt flats. Dust, dead consoles, and one dish that still ticks toward the sky.',
            'objectives' => 'Establish the empty station and the reason to enter.',
            'session_number' => 1,
            'requires_choice' => false,
        ]);

        Event::query()->create([
            'chapter_id' => $station->id,
            'position' => 2,
            'title' => 'The repeating signal',
            'content' => 'Inside, a damaged transmitter is still receiving a repeating pulse. First the player meets it, then they work the dying station, then they commit: answer, trace, or shut it down.',
            'objectives' => 'Three playable turns: identity, method, then a session-ending commitment.',
            'session_number' => 1,
            'requires_choice' => true,
        ]);

        Event::query()->create([
            'chapter_id' => $beyond->id,
            'position' => 1,
            'title' => 'The consequence arrives',
            'content' => 'The player\'s session-one commitment pays off at once: a last reply, a set of coordinates the maps deny, or a locally stored fragment the transmitter was never meant to keep.',
            'objectives' => 'Reveal the consequence of answering, tracing, or shutting the signal down.',
            'session_number' => 2,
            'requires_choice' => false,
        ]);

        Event::query()->create([
            'chapter_id' => $beyond->id,
            'position' => 2,
            'title' => 'The denied location',
            'content' => 'The origin, the reply, and the archive reconverge. The player meets the consequence, presses it, then commits before the last frequency: follow the origin, keep a copy, or seal the site.',
            'objectives' => 'Three playable turns: meet the consequence, press it, then a session-ending commitment. The story does not end here.',
            'session_number' => 2,
            'requires_choice' => true,
        ]);

        Event::query()->create([
            'chapter_id' => $frequency->id,
            'position' => 1,
            'title' => 'The last room',
            'content' => 'The unfinished consequence of session two is already here. The remaining message occupies one last room.',
            'objectives' => 'Put the player inside the last room without inventing a new mystery.',
            'session_number' => 3,
            'requires_choice' => false,
        ]);

        Event::query()->create([
            'chapter_id' => $frequency->id,
            'position' => 2,
            'title' => 'What remains of the message',
            'content' => 'The remaining words are in reach. The player enters, decides how to hear them, then chooses whether to preserve the message, destroy it, or leave the frequency running. After that, the station goes silent and the story ends.',
            'objectives' => 'Three playable turns: enter, hear, then resolve the signal and the player\'s part in both.',
            'session_number' => 3,
            'requires_choice' => true,
        ]);
    }

    private function seedAdaptation(Story $story): void
    {
        $adaptation = StoryAdaptation::query()->create([
            'story_id' => $story->id,
            'adaptation_status' => AdaptationStatusEnum::COMPLETED,
            'boiler_enabled' => true,
            'ip_trimming' => $this->ipTrimming(),
            'voice_profile' => $this->voiceProfile(),
            'story_session_map' => $this->storySessionMap(),
        ]);

        foreach ([1, 2, 3] as $sessionNumber) {
            $this->seedSession($story, $adaptation, $sessionNumber);
        }
    }

    private function seedSession(Story $story, StoryAdaptation $adaptation, int $sessionNumber): void
    {
        $events = Event::query()
            ->join('chapters', 'chapters.id', '=', 'events.chapter_id')
            ->where('chapters.story_id', $story->id)
            ->where('events.session_number', $sessionNumber)
            ->orderBy('events.position')
            ->get(['events.id']);

        $eventIds = $events->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $anchorId = $eventIds[0] ?? null;

        $session = SessionAdaptation::query()->create([
            'story_adaptation_id' => $adaptation->id,
            'session_number' => $sessionNumber,
            'session_status' => SessionAdaptationStatusEnum::COMPLETED,
            'boiler_status' => BoilerStatus::FINALIZED,
            'boiler_generated_at' => now(),
            'boiler_finalized_at' => now(),
            'entry_point_diagnosis' => $this->entryPoint($sessionNumber),
            'session_architecture' => $this->architecture($sessionNumber),
            'session_choice_design' => $this->choiceDesign($sessionNumber),
            'choice_consequence_map' => $this->consequenceMap($sessionNumber),
            'session_close_design' => $this->closeDesign($sessionNumber),
            'boiler_final_cut' => [
                'playable_promise' => $this->boilerPromise($sessionNumber),
            ],
            'editorial_verification' => [
                'final_verdict' => [
                    'production_status' => 'READY',
                ],
            ],
        ]);

        $cut = PlayableSessionCut::query()->create([
            'story_id' => $story->id,
            'session_adaptation_id' => $session->id,
            'session_number' => $sessionNumber,
            'cut_key' => 'standard',
            'status' => 'finalized',
        ]);

        foreach ($this->moments($sessionNumber) as $moment) {
            PlayableMoment::query()->create([
                'playable_session_cut_id' => $cut->id,
                'moment_key' => $moment['moment_key'],
                'moment_order' => $moment['moment_order'],
                'anchor_event_id' => $anchorId,
                'source_event_ids' => $eventIds,
                'scene_card' => $moment,
            ]);
        }
    }

    private function assembleRuntimePrompts(Story $story): void
    {
        $story->load(['creator', 'adaptation.sessionAdaptations']);

        $builder = app(RuntimeNarratorTemplateBuilder::class);
        $validator = app(RuntimeNarratorPromptValidator::class);

        foreach ($story->adaptation->sessionAdaptations as $session) {
            $prompt = $builder->build($story, $session);
            $validator->validate($prompt, true);

            $session->update([
                'runtime_narrator_prompt' => $prompt,
                'runtime_narrator_assembled_at' => now(),
                'session_status' => SessionAdaptationStatusEnum::COMPLETED,
                'boiler_error' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ipTrimming(): array
    {
        return [
            'story_spine' => [
                'protagonist' => 'you',
                'dramatic_question' => 'What will you do with the last signal before the station goes silent forever?',
                'world' => 'An abandoned communications outpost on salt flats, still catching one repeating transmission.',
                'major_turning_points' => [
                    [
                        'reference' => 'Session 1 close',
                        'description' => 'The player answers, traces, or shuts down the signal.',
                    ],
                    [
                        'reference' => 'Session 2 close',
                        'description' => 'The player follows the origin, keeps a copy, or seals the site.',
                    ],
                    [
                        'reference' => 'Session 3 close',
                        'description' => 'The player preserves, destroys, or abandons the remaining message.',
                    ],
                ],
                'irreversible_events' => [
                    [
                        'event' => 'The station is already empty when the player arrives.',
                        'why_fixed' => 'The premise is an abandoned last transmission, not a rescue.',
                    ],
                    [
                        'event' => 'The story ends after session 3.',
                        'why_fixed' => 'There is no fourth session.',
                    ],
                ],
            ],
            'world_rules' => [
                'physics_technology' => [
                    [
                        'rule' => 'The transmitter can receive, answer, be traced, or be powered down.',
                        'evidence' => 'Damaged but still cycling a pulse.',
                    ],
                ],
                'creatures_entities' => [],
                'social_systems' => [
                    [
                        'rule' => 'No living staff remain. Automated systems still flag the pulse.',
                        'evidence' => 'The fence is open. The halls are dust.',
                    ],
                ],
                'what_cannot_exist' => [
                    [
                        'thing' => 'A populated town, extra playable locations inside a session, or a rescue team arriving in time to take the choice away.',
                        'why' => 'The test story must stay inside the station, then the denied site, then one last room.',
                    ],
                ],
                'geography_locations' => [
                    [
                        'rule' => 'Session 1 is the station. Session 2 is the place the maps deny. Session 3 is the last frequency room, reached only because of the player\'s previous commitments.',
                        'evidence' => 'The dish ticks. The maps have a blank. One room remains.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function voiceProfile(): array
    {
        return [
            'profile_type' => 'NOVELIST',
            'voice_anchor' => [
                [
                    'mode' => 'cold open',
                    'source' => 'internal test story',
                    'techniques' => 'concrete objects, short sentences, second person',
                    'session_relevance' => 'all_sessions',
                    'prose' => 'The dish ticks once, then again. Dust hangs in the stairwell as if someone meant to come back. The pulse in the transmitter is the only thing in the building that still wants an answer.',
                ],
            ],
            'anchor_card' => [
                'Second person present. Concrete objects over mood.',
                'Keep turns under 120 words.',
                'End on a live object or unfinished action, never a question to the player.',
            ],
            'runtime_self_check' => [
                'Cut any sentence that restates the room.',
                'If a sentence asks the player what to do, delete it.',
                'If a banned alignment word appears, rewrite the line.',
            ],
            'author_voice_dna_profile' => [
                'signature_writing_techniques' => [
                    [
                        'name' => 'Object pressure',
                        'frequency' => 'every turn',
                        'why_this_author' => 'The dish, the pulse, and the maps do the work of exposition.',
                    ],
                ],
                'sentence_level_patterns' => [
                    'average_sentence_length' => '12-16 words',
                    'cadence_variation' => 'short then slightly longer',
                    'clause_structure_preference' => 'simple independent clauses',
                    'punctuation_habits' => 'periods. Almost no em dashes.',
                ],
                'paragraph_architecture' => [
                    'pattern' => 'two or three short paragraphs',
                    'transition_method' => 'object to object',
                ],
                'diction_fingerprint' => [
                    'register_and_formality' => 'plain, technical, unsentimental',
                    'word_frequency_patterns' => 'signal, dish, pulse, maps, station',
                ],
                'show_explain_ratio' => [
                    'enforcement_note' => 'Show the pulse. Do not explain what it means until the player forces meaning.',
                ],
                'dialogue_fingerprint_per_character' => [],
            ],
            'master_rule_1_hard_bans' => [
                'ip_specific_bans' => [
                    [
                        'ban' => 'A living staff member returning to take the choice away.',
                        'positive_replacement' => 'Let machines, logs, and the pulse answer.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storySessionMap(): array
    {
        return [
            'alignment_labels' => [
                [
                    'label' => 'The one who follows the signal into the dark.',
                    'maps_to_internal' => 'chaotic',
                    'behavioral_markers' => ['restores power first', 'traces before asking', 'moves toward the unknown'],
                    'narrative_consequences' => 'Impulse becomes a path off the map.',
                    'voice_signature' => 'Forward, unhesitating.',
                ],
                [
                    'label' => 'The one who listens before walking away.',
                    'maps_to_internal' => 'lawful',
                    'behavioral_markers' => ['inspects the transmitter', 'answers only after reading the pulse', 'preserves what can still be read'],
                    'narrative_consequences' => 'Caution becomes witness.',
                    'voice_signature' => 'Measured, exact.',
                ],
                [
                    'label' => 'The one who keeps the station\'s secret.',
                    'maps_to_internal' => 'neutral',
                    'behavioral_markers' => ['listens without answering', 'shuts the dish down', 'leaves the frequency running without claiming it'],
                    'narrative_consequences' => 'Reserve becomes a closed door.',
                    'voice_signature' => 'Quiet, withheld.',
                ],
            ],
            'session_allocation' => [
                [
                    'session_number' => 1,
                    'chapters_covered' => 'The Station',
                    'primary_dramatic_question' => 'Will you answer, trace, or silence the last signal?',
                    'emotional_register' => 'unease turning into a public commitment',
                ],
                [
                    'session_number' => 2,
                    'chapters_covered' => 'Beyond the Maps',
                    'primary_dramatic_question' => 'Will you follow the origin, keep a copy, or seal the site?',
                    'emotional_register' => 'consequence turning into a second commitment',
                ],
                [
                    'session_number' => 3,
                    'chapters_covered' => 'The Last Frequency',
                    'primary_dramatic_question' => 'What remains of the message once you have followed your choice?',
                    'emotional_register' => 'authorship turning into an ending',
                ],
            ],
            'arc_progression' => [
                [
                    'session_number' => 1,
                    'opens_with' => '',
                ],
                [
                    'session_number' => 2,
                    'opens_with' => 'The coordinates point toward a location the station\'s maps insist does not exist.',
                ],
                [
                    'session_number' => 3,
                    'opens_with' => 'The last room is already carrying the unfinished frequency.',
                ],
            ],
            'story_guard_canon' => [
                'layer_2_character_canon' => [],
                'layer_3_narrative_canon' => [
                    [
                        'beat' => 'Session 1 must end on answering, tracing, or shutting down the signal.',
                        'why_required' => 'That commitment is the first session Completion Card.',
                    ],
                    [
                        'beat' => 'Session 2 must end on following the origin, keeping a copy, or sealing the site.',
                        'why_required' => 'That commitment is the second session Completion Card. The story does not end here.',
                    ],
                    [
                        'beat' => 'Session 3 must end the story after the fate of the message is chosen.',
                        'why_required' => 'There is no session 4.',
                    ],
                ],
            ],
            'world_reactivity_rules' => [
                'reactivity_categories' => [
                    [
                        'how_it_triggers' => 'restores power or inspects hardware',
                        'when_it_triggers' => 'session 1, first action',
                        'how_it_manifests' => 'Lights, logs, or the pulse itself change in a way that cannot be undone.',
                    ],
                    [
                        'how_it_triggers' => 'answers, traces, or silences the signal',
                        'when_it_triggers' => 'session 1 close',
                        'how_it_manifests' => 'Session 2 opens on that specific unfinished consequence.',
                    ],
                    [
                        'how_it_triggers' => 'follows the origin, keeps a copy, or seals the site',
                        'when_it_triggers' => 'session 2 close',
                        'how_it_manifests' => 'Session 3 opens on that specific unfinished last room.',
                    ],
                ],
                'timing_rules' => [
                    [
                        'category' => 'hardware',
                        'timing' => 'immediate',
                    ],
                    [
                        'category' => 'commitment',
                        'timing' => 'next session opening',
                    ],
                ],
            ],
            'persistent_state_schema' => [
                'objects' => [
                    [
                        'name' => 'damaged transmitter',
                        'type' => 'machine',
                        'initial_state' => 'receiving a repeating pulse',
                        'tracked_attributes' => ['powered', 'open_channel', 'silenced'],
                        'persistence_requirement' => 'Remember whether it was restored, preserved, or shut down.',
                    ],
                    [
                        'name' => 'last signal',
                        'type' => 'transmission',
                        'initial_state' => 'repeating, unanswered',
                        'tracked_attributes' => ['answered', 'traced', 'archived', 'destroyed'],
                        'persistence_requirement' => 'The player\'s last decision about the message must survive the turn.',
                    ],
                ],
                'npcs' => [],
                'world_flags' => [
                    [
                        'name' => 'station_restored',
                        'initial_value' => 'false',
                        'possible_values' => ['false', 'true'],
                    ],
                    [
                        'name' => 'message_fate',
                        'initial_value' => 'unresolved',
                        'possible_values' => ['unresolved', 'preserved', 'destroyed', 'left_running'],
                    ],
                ],
                'player_historical_archive_categories' => [
                    [
                        'category' => 'promises_made',
                        'definition' => 'Commitments made to the signal or to silence.',
                    ],
                    [
                        'category' => 'secrets',
                        'definition' => 'What the player learned from the pulse.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boilerPromise(int $sessionNumber): string
    {
        return match ($sessionNumber) {
            1 => 'Exactly three rooms. Each player action completes one room. S1_M01 is identity. S1_M02 is method. S1_M03 takes S1_C4 and completes the session. Never invent a fourth room or a second turn inside any room. Always write state_delta and alignment_tally_delta.',
            2 => 'Exactly three rooms. Honour the carried Session 1 commitment. S2_M01 meets the consequence. S2_M02 presses it. S2_M03 takes S2_C4 and completes the session. The story does not end here. Always write state_delta and alignment_tally_delta.',
            default => 'Exactly three rooms. Honour the carried Session 2 commitment. S3_M01 enters the last room. S3_M02 hears the remaining words. S3_M03 takes S3_C4 and ends the story. Never invent a fourth room. Always write state_delta and alignment_tally_delta.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPoint(int $sessionNumber): array
    {
        return match ($sessionNumber) {
            1 => [
                'start_event_position' => 1,
                'cold_open' => 'The access road ends at a fence that nobody bothered to close. Beyond it, the communication station sits dark against the salt flats, one dish still ticking as if it has somewhere to look. Inside, a damaged transmitter keeps repeating a signal that should have died years ago. The repeating pulse is the only thing in the building that still wants an answer.',
                'emotional_promise' => 'In a few minutes you will have to decide whether the last signal is answered, followed, or ended.',
                'freeform_input_hint' => 'Restore the lights, inspect the dish, or just listen.',
                'format_specific_cut' => [
                    'must_reintroduce' => '',
                ],
                'first_choice_spec' => [
                    'setup_prose' => 'The transmitter is still cycling. Power, inspection, or silence are all available at once.',
                    'question' => 'How do you meet the pulse?',
                ],
            ],
            2 => [
                'start_event_position' => 3,
                'cold_open' => 'The maps insist this place does not exist. The coordinates from the station brought you here anyway. The last signal is closer now, and whatever you promised it is waiting to be finished.',
                'emotional_promise' => 'The consequence of your last choice arrives immediately. You will meet it, press it, then commit again before the last room.',
                'freeform_input_hint' => 'Follow what your last choice left unfinished.',
                'format_specific_cut' => [
                    'must_reintroduce' => 'Carry forward whether they answered, traced, or shut the transmitter down.',
                ],
                'first_choice_spec' => [
                    'setup_prose' => 'The consequence of session one is already in the room.',
                    'question' => 'How do you meet what your last choice summoned?',
                ],
            ],
            default => [
                'start_event_position' => 5,
                'cold_open' => 'The last room is already carrying the unfinished frequency. The remaining message is here, not elsewhere. Whatever you promised at the denied site is waiting to be finished in this one place.',
                'emotional_promise' => 'You will enter, hear what remains, then decide whether the last words survive.',
                'freeform_input_hint' => 'Open the remaining channel, read the last log, or stand in the dark.',
                'format_specific_cut' => [
                    'must_reintroduce' => 'Carry forward whether they followed the origin, kept a copy, or sealed the site.',
                ],
                'first_choice_spec' => [
                    'setup_prose' => 'The last room is already here. Entering it is the first choice.',
                    'question' => 'How do you enter the last frequency?',
                ],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function architecture(int $sessionNumber): array
    {
        return match ($sessionNumber) {
            1 => [
                'beat_map' => [
                    [
                        'beat_type' => 'SETUP',
                        'time_range' => 'turn 1',
                        'moment' => 'Empty station, damaged transmitter, repeating pulse. IDENTITY choice.',
                        'choice_slot' => 'S1_C1',
                        'dramatic_function' => 'First engagement that the world remembers.',
                    ],
                    [
                        'beat_type' => 'ESCALATION',
                        'time_range' => 'turn 2',
                        'moment' => 'Work the dying station. METHODOLOGY choice.',
                        'choice_slot' => 'S1_C2',
                        'dramatic_function' => 'A method that changes how the later commitment lands.',
                    ],
                    [
                        'beat_type' => 'RESOLUTION',
                        'time_range' => 'turn 3',
                        'moment' => 'SESSION_END_HOOK: answer, trace, or shut down.',
                        'choice_slot' => 'S1_C4',
                        'dramatic_function' => 'The commitment the first Completion Card must speak.',
                    ],
                ],
                'next_session_awareness' => [
                    'seed_for_next_session' => 'Session 2 opens on the unfinished consequence of S1_C4, not a generic cliffhanger.',
                ],
            ],
            2 => [
                'beat_map' => [
                    [
                        'beat_type' => 'SETUP',
                        'time_range' => 'turn 1',
                        'moment' => 'The Session 1 commitment pays off immediately.',
                        'choice_slot' => 'S2_C1',
                        'dramatic_function' => 'Let the player see what their last choice did.',
                    ],
                    [
                        'beat_type' => 'ESCALATION',
                        'time_range' => 'turn 2',
                        'moment' => 'Press the consequence. METHODOLOGY choice.',
                        'choice_slot' => 'S2_C2',
                        'dramatic_function' => 'A method that makes the second commitment expensive.',
                    ],
                    [
                        'beat_type' => 'RESOLUTION',
                        'time_range' => 'turn 3',
                        'moment' => 'SESSION_END_HOOK: follow the origin, keep a copy, or seal the site.',
                        'choice_slot' => 'S2_C4',
                        'dramatic_function' => 'The commitment the second Completion Card must speak. The story does not end.',
                    ],
                ],
                'next_session_awareness' => [
                    'seed_for_next_session' => 'Session 3 opens on the unfinished last room of S2_C4, not a new mystery.',
                ],
            ],
            default => [
                'beat_map' => [
                    [
                        'beat_type' => 'SETUP',
                        'time_range' => 'turn 1',
                        'moment' => 'Enter the last frequency room.',
                        'choice_slot' => 'S3_C1',
                        'dramatic_function' => 'Put the player inside the remaining message.',
                    ],
                    [
                        'beat_type' => 'ESCALATION',
                        'time_range' => 'turn 2',
                        'moment' => 'Hear the remaining words. METHODOLOGY choice.',
                        'choice_slot' => 'S3_C2',
                        'dramatic_function' => 'Make the last authorship expensive.',
                    ],
                    [
                        'beat_type' => 'RESOLUTION',
                        'time_range' => 'turn 3',
                        'moment' => 'Preserve, destroy, or leave the remaining message. Story ends.',
                        'choice_slot' => 'S3_C4',
                        'dramatic_function' => 'Final authorship over the signal.',
                    ],
                ],
                'next_session_awareness' => [
                    'seed_for_next_session' => 'None. The story ends here.',
                ],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function choiceDesign(int $sessionNumber): array
    {
        $choices = match ($sessionNumber) {
            1 => [
                $this->choice(
                    'S1_C1',
                    'IDENTITY',
                    'how the player first touches the dead station',
                    'The pulse is already in the room. Power, inspection, and withheld listening are all available.',
                    'How do you meet the last signal?',
                    [
                        ['A', 'Restore power to the abandoned station.', 'Lights return. The world remembers that you brought the station back.'],
                        ['B', 'Inspect the damaged transmitter before answering it.', 'You learn how the pulse is built. Caution becomes a method.'],
                        ['C', 'Listen to the signal without responding.', 'You hear more than a reply would have given you, and you remain uncommitted.'],
                    ],
                    'The transmitter is still cycling. A method is now required.',
                ),
                $this->choice(
                    'S1_C2',
                    'METHODOLOGY',
                    'how the player works the dying station',
                    'The pulse is readable now. The buffer, the dish, and the doors are all available.',
                    'How do you work the dying station?',
                    [
                        ['A', 'Decode the transmitter buffer.', 'The stored fragments become readable before any reply is spent.'],
                        ['B', 'Climb the dish and lock the heading.', 'The skyward machine becomes a tool in your hands.'],
                        ['C', 'Lock the station doors and work from inside.', 'The building is sealed. Whatever you do next stays in this room first.'],
                    ],
                    'The transmitter is still cycling, and a commitment is now required.',
                ),
                $this->choice(
                    'S1_C4',
                    'SESSION_END_HOOK',
                    'what the player does with the last signal',
                    'The pulse will not wait. Answering, tracing, or shutting it down all leave the station changed.',
                    'What do you do with the last signal?',
                    [
                        ['A', 'You chose to answer the signal.', 'A reply leaves the dish. Something beyond the station now has your voice.'],
                        ['B', 'You chose to trace the signal to its source.', 'Coordinates appear that the station maps refuse to name.'],
                        ['C', 'You chose to shut the transmitter down.', 'The pulse stops. A local fragment remains that the dish was never meant to keep.'],
                    ],
                    'Session 1 ends. Session 2 opens on that specific unfinished consequence.',
                ),
            ],
            2 => [
                $this->choice(
                    'S2_C1',
                    'IDENTITY',
                    'how the player meets the consequence they earned',
                    'The Session 1 commitment is already here as a reply, a denied location, or a stored fragment.',
                    'How do you meet what your last choice summoned?',
                    [
                        ['A', 'Press toward the origin of the signal.', 'The source becomes visible as a place, not a theory.'],
                        ['B', 'Wait for the last response to finish arriving.', 'The reply completes in front of you.'],
                        ['C', 'Search the local archive for what the transmitter stored.', 'A fragment the dish was not supposed to keep is now in your hands.'],
                    ],
                    'The consequence is in reach. A method is now required.',
                ),
                $this->choice(
                    'S2_C2',
                    'METHODOLOGY',
                    'how the player presses the consequence',
                    'The origin, the channel, and the fragment are all in the same denied place.',
                    'How do you press what you found?',
                    [
                        ['A', 'Enter the denied room.', 'The blank on the map becomes a threshold you cross.'],
                        ['B', 'Hold the channel open.', 'The remaining words keep arriving while you stay.'],
                        ['C', 'Take the fragment with you.', 'Whatever the station stored is now in your possession.'],
                    ],
                    'One more commitment remains before the last frequency.',
                ),
                $this->choice(
                    'S2_C4',
                    'SESSION_END_HOOK',
                    'what the player commits to before the last room',
                    'The denied site will not hold this choice. Following, copying, or sealing all leave a last room waiting.',
                    'What do you commit to before the last frequency?',
                    [
                        ['A', 'You chose to follow the origin all the way.', 'The last room is the origin itself.'],
                        ['B', 'You chose to keep a copy of what you found.', 'The last room is a copy already in your hands.'],
                        ['C', 'You chose to seal the site.', 'The last room waits behind a door you closed.'],
                    ],
                    'Session 2 ends. Session 3 opens on that specific unfinished last room.',
                ),
            ],
            default => [
                $this->choice(
                    'S3_C1',
                    'IDENTITY',
                    'how the player enters the last room',
                    'The remaining message occupies one last room. Opening it, reading it, or standing still are all available.',
                    'How do you enter the last frequency?',
                    [
                        ['A', 'Open the remaining channel.', 'The last words have a live path into the room.'],
                        ['B', 'Read the last log.', 'The station\'s own record of the message is in reach.'],
                        ['C', 'Stand in the dark and wait.', 'You enter without touching the hardware first.'],
                    ],
                    'The remaining words are now in the room. A method is required.',
                ),
                $this->choice(
                    'S3_C2',
                    'METHODOLOGY',
                    'how the player hears the remaining words',
                    'The last message can be played, translated, or left unheard.',
                    'What do you do with the remaining words?',
                    [
                        ['A', 'Play the remaining words aloud.', 'The last signal occupies the air of this room.'],
                        ['B', 'Translate the remaining words.', 'Meaning arrives before the frequency dies.'],
                        ['C', 'Leave the remaining words unheard.', 'You will decide their fate without spending them.'],
                    ],
                    'The origin, the reply, and the archive reconverge. One last authorship remains.',
                ),
                $this->choice(
                    'S3_C4',
                    'SESSION_END_HOOK',
                    'whether the remaining message survives',
                    'The station, the source, and the fragment are all in reach. The story ends on what you do with the message.',
                    'What remains of the last signal?',
                    [
                        ['A', 'You chose to preserve the message.', 'The pulse can die. The words do not.'],
                        ['B', 'You chose to destroy the message.', 'Nothing of it will travel further than this room.'],
                        ['C', 'You chose to leave the frequency running.', 'You walk away without claiming authorship over the last word.'],
                    ],
                    'The story ends. There is no session 4.',
                ),
            ],
        };

        return [
            'branching_choices' => $choices,
            'emotional_choices' => [],
            'posture_shifts' => [],
            'scene_rules_layer_4' => [
                'tone_constraints_for_session' => match ($sessionNumber) {
                    1 => ['Spare. Technical. No rescue fantasy.'],
                    2 => ['Consequence first. No new subplot.'],
                    default => ['Last room only. No sequel hook.'],
                },
                'language_constraints_for_session' => ['No banned alignment words in narration.'],
                'thematic_constraints_for_session' => match ($sessionNumber) {
                    1 => ['Listening versus answering versus ending.'],
                    2 => ['Following versus copying versus sealing.'],
                    default => ['Authorship over a dying frequency.'],
                },
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $options
     * @return array<string, mixed>
     */
    private function choice(
        string $choiceId,
        string $category,
        string $tracks,
        string $setup,
        string $question,
        array $options,
        string $arriveAt,
    ): array {
        return [
            'choice_id' => $choiceId,
            'category' => $category,
            'beat' => $choiceId,
            'what_this_choice_tracks' => $tracks,
            'narrative_setup' => $setup,
            'choice_question' => $question,
            'options' => array_map(fn (array $option): array => [
                'label' => $option[0],
                'text' => $option[1],
                'downstream_effect' => $option[2],
            ], $options),
            'all_paths_arrive_at' => $arriveAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consequenceMap(int $sessionNumber): array
    {
        return match ($sessionNumber) {
            1 => [
                'branching_consequences' => [
                    $this->consequence('S1_C1', 'first_engagement', false, [
                        $this->path('A', 'CHAOTIC', 'The station lights return.', 'You restored the abandoned station.', 'The lights stay on when you leave.', '', 'You brought power back to a place that had given up.', 'You restored the abandoned station.'),
                        $this->path('B', 'LAWFUL', 'The transmitter\'s damage becomes readable.', 'You inspected the dish before answering it.', 'Method becomes the way you approach the last choice.', '', 'You read the pulse before you spent it.', 'You inspected the transmitter before answering it.'),
                        $this->path('C', 'NEUTRAL', 'You hear the pulse without giving it a voice.', 'You listened without responding.', 'Withholding becomes a reputation the logs can still record.', '', 'You heard more than a reply would have given you.', 'You listened to the signal without answering.'),
                    ]),
                    $this->consequence('S1_C2', 'station_method', false, [
                        $this->path('A', 'LAWFUL', 'The buffer yields stored fragments.', 'You decoded the transmitter before committing.', 'The later commitment arrives with evidence already in hand.', '', 'You read what the dish had already overheard.', 'You decoded the transmitter buffer.'),
                        $this->path('B', 'CHAOTIC', 'The dish turns under your hands.', 'You climbed the dish and locked the heading.', 'The skyward machine is now a tool you already used.', '', 'You made the dish obey before you spent the pulse.', 'You climbed the dish and locked the heading.'),
                        $this->path('C', 'NEUTRAL', 'The doors seal from inside.', 'You locked the station and worked from within.', 'Whatever happens next happens in a closed building first.', '', 'You kept the choice inside the station.', 'You locked the station doors and worked from inside.'),
                    ]),
                    $this->consequence('S1_C4', 'signal_commitment', true, [
                        $this->path('A', 'LAWFUL', 'N/A — session ends on this choice', 'Your voice is now on the frequency.', 'A final reply is forming on a frequency the maps do not name.', 'A final reply is forming on a frequency the maps do not name.', 'You put your voice on the last signal.', 'You chose to answer the signal.'),
                        $this->path('B', 'CHAOTIC', 'N/A — session ends on this choice', 'Coordinates appear that the maps refuse.', 'The coordinates point toward a location the station\'s maps insist does not exist.', 'The coordinates point toward a location the station\'s maps insist does not exist.', 'You refused to let the pulse stay anonymous.', 'You chose to trace the signal to its source.'),
                        $this->path('C', 'NEUTRAL', 'N/A — session ends on this choice', 'The dish goes dark. A local fragment remains.', 'The local archive still holds a fragment the transmitter was never meant to keep.', 'The local archive still holds a fragment the transmitter was never meant to keep.', 'You ended the pulse and kept the remainder.', 'You chose to shut the transmitter down.'),
                    ]),
                ],
                'freeform_guidelines' => [
                    $this->freeform('S1_C1', 'A', 'restoring power or waking the building', 'flipping breakers, repairing lights, feeding the dish', 'The station can wake. It cannot sprout a living crew.'),
                    $this->freeform('S1_C1', 'B', 'inspecting hardware before committing', 'opening panels, reading logs, mapping the pulse', 'Inspection can reveal the mechanism. It cannot skip the later commitment.'),
                    $this->freeform('S1_C1', 'C', 'listening without giving the pulse a reply', 'headphones, silence, recording without transmit', 'Listening can deepen knowledge. It cannot pretend the later choice was already made.'),
                    $this->freeform('S1_C2', 'A', 'decoding stored fragments', 'opening the buffer, reading logs, extracting packets', 'The buffer can speak. It cannot skip the session-end commitment.'),
                    $this->freeform('S1_C2', 'B', 'climbing or aiming the dish', 'locking the heading, turning the motor, bracing the mount', 'The dish can be aimed. A second location cannot appear yet.'),
                    $this->freeform('S1_C2', 'C', 'sealing the building', 'locking doors, shutting vents, working from inside', 'The station can be sealed. The later commitment still has to be taken.'),
                    $this->freeform('S1_C4', 'A', 'answering the signal', 'speaking, transmitting, sending a ping', 'An answer leaves the building. It does not invent a friendly operator on the other end.'),
                    $this->freeform('S1_C4', 'B', 'tracing the source', 'triangulating, following coordinates, locking the heading', 'A trace produces a denied location. It does not skip session 2.'),
                    $this->freeform('S1_C4', 'C', 'shutting the transmitter down', 'killing power, cutting the feed, silencing the dish', 'Silence can be chosen. A stored fragment still remains for session 2.'),
                ],
            ],
            2 => [
                'branching_consequences' => [
                    $this->consequence('S2_C1', 'consequence_meeting', false, [
                        $this->path('A', 'CHAOTIC', 'The origin becomes a place.', 'You followed the signal all the way to its source.', 'The source is no longer a theory.', '', 'The blank on the map now has a room.', 'You followed the signal to its source.'),
                        $this->path('B', 'LAWFUL', 'The last reply finishes arriving.', 'You stayed long enough to hear the truth.', 'The reply completes in front of you.', '', 'Someone, or something, used its last words.', 'You waited for the last response.'),
                        $this->path('C', 'NEUTRAL', 'The local fragment is in your hands.', 'You recovered what the transmitter stored.', 'The archive yields a message the dish was not supposed to keep.', '', 'The station had already overheard itself.', 'You searched the local archive.'),
                    ]),
                    $this->consequence('S2_C2', 'consequence_method', false, [
                        $this->path('A', 'CHAOTIC', 'You cross the denied threshold.', 'You entered the room the maps refused.', 'The last room will have to deal with a door you already opened.', '', 'The blank on the map now has a floor.', 'You entered the denied room.'),
                        $this->path('B', 'LAWFUL', 'The channel stays open under your hands.', 'You held the remaining words in the air.', 'The last room will receive a channel you already kept alive.', '', 'You refused to let the last words finish without a witness.', 'You held the channel open.'),
                        $this->path('C', 'NEUTRAL', 'The fragment leaves the archive.', 'You took the stored remnant with you.', 'The last room will have to deal with a copy already in your possession.', '', 'What the station stored is no longer only the station\'s.', 'You took the fragment with you.'),
                    ]),
                    $this->consequence('S2_C4', 'last_room_commitment', true, [
                        $this->path('A', 'CHAOTIC', 'N/A — session ends on this choice', 'You keep walking toward the origin.', 'The last room is the origin itself, still carrying the unfinished frequency.', 'The last room is the origin itself, still carrying the unfinished frequency.', 'You refused to stop short of the source.', 'You chose to follow the origin all the way.'),
                        $this->path('B', 'LAWFUL', 'N/A — session ends on this choice', 'You keep a copy of what you found.', 'The last room is a copy you already made, waiting for what you will do with it.', 'The last room is a copy you already made, waiting for what you will do with it.', 'You kept the words before the frequency could die.', 'You chose to keep a copy of what you found.'),
                        $this->path('C', 'NEUTRAL', 'N/A — session ends on this choice', 'You seal the denied site.', 'The last room waits behind a sealed door you now have to open or abandon.', 'The last room waits behind a sealed door you now have to open or abandon.', 'You closed the site and left the last room for later.', 'You chose to seal the site.'),
                    ]),
                ],
                'freeform_guidelines' => [
                    $this->freeform('S2_C1', 'A', 'going toward the origin', 'walking the heading, entering the denied site', 'The source can be reached. The story cannot end yet.'),
                    $this->freeform('S2_C1', 'B', 'waiting for the last reply', 'holding the channel open, listening to the end', 'The reply can finish. It cannot postpone the later commitment.'),
                    $this->freeform('S2_C1', 'C', 'searching stored fragments', 'opening local logs, pulling the buffer', 'The archive can yield a remnant. It cannot hide session 3.'),
                    $this->freeform('S2_C2', 'A', 'entering the denied room', 'crossing the threshold, stepping into the blank', 'The room can be entered. The last frequency is still ahead.'),
                    $this->freeform('S2_C2', 'B', 'holding the channel open', 'keeping the feed live, refusing to hang up', 'The channel can stay open. The later commitment still has to be taken.'),
                    $this->freeform('S2_C2', 'C', 'taking the fragment', 'copying, pocketing, carrying the remnant', 'The fragment can leave the archive. Session 3 still has to resolve it.'),
                    $this->freeform('S2_C4', 'A', 'following the origin all the way', 'continuing toward the source, refusing to stop', 'The origin can be promised. The last room is still session 3.'),
                    $this->freeform('S2_C4', 'B', 'keeping a copy', 'recording, duplicating, carrying the remnant forward', 'A copy can be kept. The last authorship is still ahead.'),
                    $this->freeform('S2_C4', 'C', 'sealing the site', 'locking the denied place, closing the door', 'The site can be sealed. The last room still waits.'),
                ],
            ],
            default => [
                'branching_consequences' => [
                    $this->consequence('S3_C1', 'last_room_entry', false, [
                        $this->path('A', 'CHAOTIC', 'The remaining channel opens.', 'You opened a live path for the last words.', 'The last message has air to occupy.', '', 'The last room is no longer sealed against the frequency.', 'You opened the remaining channel.'),
                        $this->path('B', 'LAWFUL', 'The last log is in your hands.', 'You read the station\'s own record of the message.', 'Meaning arrives as a document before a voice.', '', 'The station had already written down what it overheard.', 'You read the last log.'),
                        $this->path('C', 'NEUTRAL', 'You enter without touching the hardware.', 'You stood in the dark and waited.', 'The last decision will not be able to pretend you never arrived.', '', 'You entered the last room without claiming it first.', 'You stood in the dark and waited.'),
                    ]),
                    $this->consequence('S3_C2', 'last_words_method', false, [
                        $this->path('A', 'CHAOTIC', 'The remaining words occupy the air.', 'You played the last signal aloud.', 'The final authorship happens after the room has already heard it.', '', 'The last frequency was given a voice in this room.', 'You played the remaining words aloud.'),
                        $this->path('B', 'LAWFUL', 'Meaning arrives before the pulse dies.', 'You translated the remaining words.', 'The last choice is made with the message already understood.', '', 'You made the last words readable before you spent them.', 'You translated the remaining words.'),
                        $this->path('C', 'NEUTRAL', 'The words stay unspent.', 'You left the remaining words unheard.', 'The last authorship happens without spending the message first.', '', 'You refused to spend the last words before deciding their fate.', 'You left the remaining words unheard.'),
                    ]),
                    $this->consequence('S3_C4', 'message_fate', true, [
                        $this->path('A', 'LAWFUL', 'N/A — session ends on this choice', 'The message is copied, stored, or carried.', '', '', 'The pulse can die. The words do not.', 'You chose to preserve the message.'),
                        $this->path('B', 'CHAOTIC', 'N/A — session ends on this choice', 'The remaining message is destroyed.', '', '', 'Nothing of it travels further than this room.', 'You chose to destroy the message.'),
                        $this->path('C', 'NEUTRAL', 'N/A — session ends on this choice', 'You leave the frequency running and walk away.', '', '', 'The last word is not yours to finish.', 'You chose to leave the frequency running.'),
                    ]),
                ],
                'freeform_guidelines' => [
                    $this->freeform('S3_C1', 'A', 'opening the remaining channel', 'switching the feed, unblocking the path', 'The channel can open. A new location cannot appear.'),
                    $this->freeform('S3_C1', 'B', 'reading the last log', 'opening files, translating headers, paging the record', 'The log can be read. The last authorship is still ahead.'),
                    $this->freeform('S3_C1', 'C', 'standing still in the last room', 'waiting, listening, refusing to touch hardware', 'Waiting can be an entry. It cannot skip the later choices.'),
                    $this->freeform('S3_C2', 'A', 'playing the remaining words', 'broadcasting, speaking them, letting the room hear', 'The words can occupy the air. The story still has to end on their fate.'),
                    $this->freeform('S3_C2', 'B', 'translating the remaining words', 'decoding, parsing, making meaning', 'Translation is allowed. A sequel is not.'),
                    $this->freeform('S3_C2', 'C', 'leaving the words unheard', 'refusing playback, keeping the buffer closed', 'Withholding is allowed. Pretending the last choice was already made is not.'),
                    $this->freeform('S3_C4', 'A', 'preserving the remaining message', 'copying, recording, carrying it out', 'Preservation is allowed. Resurrection of the station staff is not.'),
                    $this->freeform('S3_C4', 'B', 'destroying the remaining message', 'wiping, burning, overwriting the buffer', 'Destruction is allowed. Pretending the player never heard it is not.'),
                    $this->freeform('S3_C4', 'C', 'leaving the frequency running', 'walking away, refusing authorship', 'Leaving is allowed. A sequel hook is not.'),
                ],
            ],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $paths
     * @return array<string, mixed>
     */
    private function consequence(string $choiceId, string $dimension, bool $sessionEnd, array $paths): array
    {
        return [
            'choice_id' => $choiceId,
            'tracked_dimension' => $dimension,
            'is_session_end_hook' => $sessionEnd,
            'paths' => $paths,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function path(
        string $label,
        string $alignment,
        string $immediate,
        string $echo,
        string $payoff,
        string $opening,
        string $ledger,
        string $definingLine,
    ): array {
        return [
            'label' => $label,
            'alignment' => $alignment,
            'immediate_effect' => $immediate,
            'current_session_echo' => $echo,
            'next_session_payoff' => $payoff,
            'next_session_opening' => $opening,
            'later_session_legacy' => $payoff,
            'defining_line_captured' => $definingLine,
            'world_state_delta' => [
                'inventory' => 'NONE',
                'npc_shifts' => [],
                'environmental_flags' => [$echo],
                'alignment_shift' => $alignment.' +1',
                'emotional_ledger_entry' => $ledger,
            ],
            'consequence_visibility' => [
                'what_changes' => $echo,
                'how_player_sees_it' => 'through the hardware in front of them',
                'when_visible_target_responses' => 1,
                'when_visible_max_responses' => 1,
            ],
            'reactivity_triggers' => [],
            'cross_episode_propagation' => [
                'resets' => [],
                'persists' => [$echo],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function freeform(string $choiceId, string $label, string $spirit, string $belongs, string $limit): array
    {
        return [
            'choice_id' => $choiceId,
            'path_label' => $label,
            'spirit' => $spirit,
            'freeform_alignment_input' => $belongs,
            'narrator_behavior' => 'Honour the intent, write it into state_delta, then reach the authored arrival.',
            'hard_limits' => [$limit],
            'fold_back_acknowledge' => 'The intent is heard.',
            'fold_back_redirect' => 'The station only offers what still exists here.',
            'fold_back_arrive_at' => 'A real change in the room they are actually in.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function closeDesign(int $sessionNumber): array
    {
        return match ($sessionNumber) {
            1 => [
                'resolution_prose' => 'You restored, inspected, or withheld, and the station kept the record. The repeating pulse is no longer an unclaimed sound. Whatever you did with it has already left the dish.',
                'hook_transition' => 'The next room is the unfinished consequence of that commitment, not a new mystery.',
                'session_end_choice' => [
                    'choice_question' => 'What do you do with the last signal?',
                    'option_a' => [
                        'text' => 'You chose to answer the signal.',
                        'next_session_opens' => 'A final reply is forming on a frequency the maps do not name.',
                    ],
                    'option_b' => [
                        'text' => 'You chose to trace the signal to its source.',
                        'next_session_opens' => 'The coordinates point toward a location the station\'s maps insist does not exist.',
                    ],
                    'option_c' => [
                        'text' => 'You chose to shut the transmitter down.',
                        'next_session_opens' => 'The local archive still holds a fragment the transmitter was never meant to keep.',
                    ],
                    'final_line' => 'The last pulse dies in the dish. The station is no longer the only thing that remembers what you chose.',
                ],
            ],
            2 => [
                'resolution_prose' => 'The denied site has already changed because of you. The origin, the copy, or the sealed door is no longer theoretical. One last room remains, and it is the unfinished consequence of this commitment.',
                'hook_transition' => 'The last frequency is already waiting in that unfinished room.',
                'session_end_choice' => [
                    'choice_question' => 'What do you commit to before the last frequency?',
                    'option_a' => [
                        'text' => 'You chose to follow the origin all the way.',
                        'next_session_opens' => 'The last room is the origin itself, still carrying the unfinished frequency.',
                    ],
                    'option_b' => [
                        'text' => 'You chose to keep a copy of what you found.',
                        'next_session_opens' => 'The last room is a copy you already made, waiting for what you will do with it.',
                    ],
                    'option_c' => [
                        'text' => 'You chose to seal the site.',
                        'next_session_opens' => 'The last room waits behind a sealed door you now have to open or abandon.',
                    ],
                    'final_line' => 'The denied site keeps the shape of your second commitment. The last room is already listening.',
                ],
            ],
            default => [
                'resolution_prose' => 'The origin of the last signal is no longer a mystery. The abandoned station has gone quiet. What remains of the message is the shape of your last decision. One dead frequency, and the knowledge that someone stood in the dark long enough to hear it.',
                'hook_transition' => '',
                'session_end_choice' => [
                    'choice_question' => 'What remains of the last signal?',
                    'option_a' => [
                        'text' => 'You chose to preserve the message.',
                        'next_session_opens' => '',
                    ],
                    'option_b' => [
                        'text' => 'You chose to destroy the message.',
                        'next_session_opens' => '',
                    ],
                    'option_c' => [
                        'text' => 'You chose to leave the frequency running.',
                        'next_session_opens' => '',
                    ],
                    'final_line' => 'The frequency goes still. What you did with its last words is the only monument the station keeps.',
                ],
            ],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moments(int $sessionNumber): array
    {
        return match ($sessionNumber) {
            1 => [
                $this->sceneCard(
                    momentKey: 'S1_M01',
                    order: 1,
                    title: 'The repeating pulse',
                    goal: 'Make the empty station, the damaged transmitter, and the pulse undeniable, then take the IDENTITY choice.',
                    pressure: 'The pulse will not wait. Power, inspection, and withheld listening are all expensive.',
                    exit: 'The first player action in this room always satisfies the exit. They have restored power, inspected the transmitter, or listened without responding. Write that into state_delta. Set exit_condition_met true. Do not hold this room for a second player turn.',
                    bridge: 'The transmitter is still cycling. The next room is method: decode, climb, or lock the doors.',
                    choiceIds: ['S1_C1'],
                    nextKey: 'S1_M02',
                    opening: 'Dust, a ticking dish, and a pulse that should have died years ago.',
                    sessionNumber: 1,
                ),
                $this->sceneCard(
                    momentKey: 'S1_M02',
                    order: 2,
                    title: 'The dying station',
                    goal: 'Force a method: decode the buffer, climb the dish, or lock the doors.',
                    pressure: 'The pulse is readable now. Leaving without a method is not available.',
                    exit: 'The first player action in this room takes S1_C2. Present the three authored options as visible_suggestions with canonical_choice_id S1_C2 and canonical_path_label A, B, or C. After that choice, exit_condition_met true. Do not add another playable turn in this room.',
                    bridge: 'The transmitter is still cycling. The next room is the commitment: answer, trace, or shut it down.',
                    choiceIds: ['S1_C2'],
                    nextKey: 'S1_M03',
                    opening: 'The station can still be worked. The pulse has not been spent.',
                    sessionNumber: 1,
                ),
                $this->sceneCard(
                    momentKey: 'S1_M03',
                    order: 3,
                    title: 'The last commitment',
                    goal: 'Force the session-end choice: answer, trace, or shut the transmitter down.',
                    pressure: 'The pulse is no longer background. Leaving without a commitment is not available.',
                    exit: 'The first player action in this room takes S1_C4. Present the three authored options as visible_suggestions with canonical_choice_id S1_C4 and canonical_path_label A, B, or C. After that choice, exit_condition_met true and the session completes. Do not add another playable turn.',
                    bridge: '',
                    choiceIds: ['S1_C4'],
                    nextKey: '',
                    opening: 'The pulse waits for a commitment, not another inspection.',
                    sessionNumber: 1,
                ),
            ],
            2 => [
                $this->sceneCard(
                    momentKey: 'S2_M01',
                    order: 1,
                    title: 'What the last choice summoned',
                    goal: 'Show the consequence of answering, tracing, or shutting down.',
                    pressure: 'The unfinished commitment is already in the room.',
                    exit: 'The first player action in this room always satisfies the exit. Honour the carried S1_C4 path, then reconverge. Do not hold this room for a second player turn.',
                    bridge: 'The consequence is in reach. The next room is method: enter, hold, or take.',
                    choiceIds: ['S2_C1'],
                    nextKey: 'S2_M02',
                    opening: 'The maps are wrong, or the station was. The consequence is already here.',
                    sessionNumber: 2,
                ),
                $this->sceneCard(
                    momentKey: 'S2_M02',
                    order: 2,
                    title: 'The denied threshold',
                    goal: 'Force a method: enter the denied room, hold the channel, or take the fragment.',
                    pressure: 'The blank on the map will not stay theoretical.',
                    exit: 'The first player action in this room takes S2_C2. Present the three authored options as visible_suggestions with canonical_choice_id S2_C2 and canonical_path_label A, B, or C. After that choice, exit_condition_met true. Do not add another playable turn in this room.',
                    bridge: 'One more commitment remains before the last frequency.',
                    choiceIds: ['S2_C2'],
                    nextKey: 'S2_M03',
                    opening: 'The denied place has a floor, a channel, and a remnant.',
                    sessionNumber: 2,
                ),
                $this->sceneCard(
                    momentKey: 'S2_M03',
                    order: 3,
                    title: 'Before the last frequency',
                    goal: 'Force the session-end choice: follow the origin, keep a copy, or seal the site.',
                    pressure: 'The last room is already waiting. This commitment decides which unfinished thing it holds.',
                    exit: 'The first player action in this room takes S2_C4. Present the three authored options as visible_suggestions with canonical_choice_id S2_C4 and canonical_path_label A, B, or C. After that choice, exit_condition_met true and the session completes. The story does not end. There is a session 3.',
                    bridge: '',
                    choiceIds: ['S2_C4'],
                    nextKey: '',
                    opening: 'The denied site waits for a second commitment, not an ending.',
                    sessionNumber: 2,
                ),
            ],
            default => [
                $this->sceneCard(
                    momentKey: 'S3_M01',
                    order: 1,
                    title: 'The last room',
                    goal: 'Put the player inside the remaining message, then take the IDENTITY choice.',
                    pressure: 'The unfinished consequence of session two is already here.',
                    exit: 'The first player action in this room always satisfies the exit. Honour the carried S2_C4 path. Do not hold this room for a second player turn.',
                    bridge: 'The remaining words are in the room. The next room is how they are heard.',
                    choiceIds: ['S3_C1'],
                    nextKey: 'S3_M02',
                    opening: 'The last room is already carrying the unfinished frequency.',
                    sessionNumber: 3,
                ),
                $this->sceneCard(
                    momentKey: 'S3_M02',
                    order: 2,
                    title: 'The remaining words',
                    goal: 'Force a method: play, translate, or leave the remaining words unheard.',
                    pressure: 'The last message can still be spent or withheld.',
                    exit: 'The first player action in this room takes S3_C2. Present the three authored options as visible_suggestions with canonical_choice_id S3_C2 and canonical_path_label A, B, or C. After that choice, exit_condition_met true. Do not add another playable turn in this room.',
                    bridge: 'The origin, the reply, and the archive are now the same decision: what remains of the message.',
                    choiceIds: ['S3_C2'],
                    nextKey: 'S3_M03',
                    opening: 'The remaining words are already in reach.',
                    sessionNumber: 3,
                ),
                $this->sceneCard(
                    momentKey: 'S3_M03',
                    order: 3,
                    title: 'What the story leaves',
                    goal: 'Resolve the message, the station, and the player\'s part. End the story.',
                    pressure: 'Preservation, destruction, and walking away are the only remaining authorship.',
                    exit: 'The first player action in this room takes S3_C4. Present the three authored options as visible_suggestions with canonical_choice_id S3_C4 and canonical_path_label A, B, or C. After that choice, exit_condition_met true and the story completes. There is no session 4.',
                    bridge: '',
                    choiceIds: ['S3_C4'],
                    nextKey: '',
                    opening: 'The last decision is already in reach.',
                    sessionNumber: 3,
                ),
            ],
        };
    }

    /**
     * @param  list<string>  $choiceIds
     * @return array<string, mixed>
     */
    private function sceneCard(
        string $momentKey,
        int $order,
        string $title,
        string $goal,
        string $pressure,
        string $exit,
        string $bridge,
        array $choiceIds,
        string $nextKey,
        string $opening,
        int $sessionNumber,
    ): array {
        return [
            'moment_key' => $momentKey,
            'moment_order' => $order,
            'title' => $title,
            'treatment' => 'compressed',
            'runtime_goal' => $goal,
            'live_pressure' => $pressure,
            'opening_image' => $opening,
            'exit_condition' => $exit,
            'next_bridge' => $bridge,
            'next_moment_key' => $nextKey,
            'choice_ids' => $choiceIds,
            'player_affordances' => [
                'Act on the hardware in front of you.',
                'Speak, transmit, listen, or refuse.',
                'Leave only after the room\'s decision is real.',
            ],
            'canon_must_keep' => [
                'The station began empty.',
                'The pulse is real.',
                'There are only three rooms in this session.',
            ],
            'allowed_variance' => [
                'How the player phrases the same commitment.',
            ],
            'forbidden_expansions' => [
                'A second player turn in this room.',
                'A fourth playable room.',
                'A living crew returning to take the choice away.',
                $sessionNumber === 3 ? 'A session 4.' : 'Skipping the remaining authored rooms.',
            ],
            'source_extracts' => [
                $opening,
            ],
            'word_budget' => [
                'target_per_turn' => 90,
            ],
            'turn_budget' => [
                'target_turns' => 1,
                'gravity_after' => 1,
            ],
        ];
    }
}
