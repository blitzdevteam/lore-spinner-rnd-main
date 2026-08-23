<?php

declare(strict_types=1);

namespace App\Http\Controllers\ChaosMode;

use App\ChaosMode\ChaosStoryConfig;
use App\Http\Controllers\Controller;
use App\Models\ChaosSession;
use App\Models\SessionAdaptation;
use App\Models\Story;
use App\Services\ChaosEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Throwable;

/**
 * Chaos Mode experimental runtime — internal testing only.
 * Production game flow uses GameController + PromptController powered by the
 * same ChaosEngineService this controller delegates to.
 *
 * Pipeline Upgrade V2 (Daniel's correction 2026-05-24): the system prompt
 * for every turn is the cached `session_adaptations.runtime_narrator_prompt`
 * produced by RuntimeNarratorAssemblyJob. The service injects four
 * runtime-only blocks into that cached body using marker tokens.
 *
 * If `runtime_narrator_prompt` is null for a session, the endpoint returns
 * 422. There is no fallback to legacy per-story partials.
 */
final class ChaosModeController extends Controller
{
    public function __construct(private readonly ChaosEngineService $engine) {}

    // -------------------------------------------------------------------------
    // Page
    // -------------------------------------------------------------------------

    public function show(): Response
    {
        $configured = ChaosStoryConfig::all();
        $slugs      = array_column($configured, 'slug');

        $stories = Story::query()
            ->whereIn('slug', $slugs)
            ->with(['adaptation', 'adaptation.sessionAdaptations', 'media'])
            ->get(['id', 'title', 'slug']);

        $payload = array_map(function (array $row) use ($stories) {
            $story = $stories->firstWhere('slug', $row['slug']);

            $hasAdaptation      = $story?->adaptation !== null;
            $sessionAdaptations = $story?->adaptation?->sessionAdaptations;
            $sessionCount       = (int) ($sessionAdaptations?->count() ?? 0);

            $v2Ready = $sessionAdaptations
                ?->contains(fn (SessionAdaptation $sa) => filled($sa->runtime_narrator_prompt))
                ?? false;

            $cover = $story?->getFirstMediaUrl('cover') ?: null;

            return [
                'slug'           => $row['slug'],
                'title'          => $row['title'],
                'tagline'        => $row['tagline'],
                'protagonist'    => $row['protagonist'],
                'available'      => $story !== null && $hasAdaptation && $sessionCount > 0 && $v2Ready,
                'total_sessions' => $sessionCount,
                'v2_ready'       => $v2Ready,
                'cover'          => $cover ?: null,
            ];
        }, $configured);

        return inertia('ChaosMode', [
            'stories' => $payload,
        ]);
    }

    // -------------------------------------------------------------------------
    // API — start, turn, continue
    // -------------------------------------------------------------------------

    public function start(Request $request): JsonResponse
    {
        $request->validate([
            'story_slug'  => ['required', 'string', 'in:' . implode(',', ChaosStoryConfig::slugs())],
            'model'       => ['nullable', 'string', 'in:' . implode(',', $this->engine->allowedModels())],
            'temperature' => ['nullable', 'numeric', 'min:0.5', 'max:1.5'],
        ]);

        $storySlug   = $request->string('story_slug')->toString();
        $model       = $request->string('model', ChaosEngineService::DEFAULT_MODEL)->toString();
        $temperature = (float) $request->input('temperature', $this->engine->defaultTemperatureFor($model));
        $storyConfig = ChaosStoryConfig::find($storySlug);

        if ($storyConfig === null) {
            return response()->json(['error' => 'Unknown story.'], 422);
        }

        $story = Story::query()
            ->where('slug', $storySlug)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null || $story->adaptation === null) {
            return response()->json(['error' => 'This story has no adaptation yet.'], 422);
        }

        $sessionContext = $this->engine->loadSessionContext($story, sessionNumber: 1, openingHandoff: null);

        if ($sessionContext === null) {
            return response()->json([
                'error' => 'This story has not been re-adapted under V2 yet. Re-run the adaptation pipeline (`php artisan stories:run-adaptation '
                    . $storySlug . ' --force`) before starting Chaos Mode.',
            ], 422);
        }

        if (empty($sessionContext['full_session_events'])) {
            return response()->json(['error' => 'This story has no events to narrate yet.'], 422);
        }

        $emptyWorldState = $this->engine->emptyWorldState();
        $emptyAlignment  = $this->engine->emptyAlignmentScaffold();

        $chaosSession = ChaosSession::create([
            'story_id'             => $story->id,
            'user_id'              => Auth::id(),
            'story_session_number' => 1,
            'model'                => $model,
            'conversation_history' => [],
            'world_state'          => $emptyWorldState,
            'alignment_scaffold'   => $emptyAlignment,
            'session_memory'       => null,
            'symbolic_memory'      => null,
            'is_climactic_choice'  => false,
            'session_complete'     => false,
            'turn_count'           => 0,
            'ip_address'           => $request->ip(),
        ]);

        $systemPrompt = $this->engine->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $emptyWorldState,
            alignmentScaffold:   $emptyAlignment,
            symbolicMemory:      null,
            isSessionStart:      true,
            isClimacticPrevious: false,
        );

        try {
            $result = $this->engine->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: [],
                playerAction:        null,
                protagonist:         $storyConfig['protagonist'],
                temperature:         $temperature,
            );

            $worldState      = $this->engine->mergeStateDelta($emptyWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($emptyAlignment, $result['alignment_tally_delta']);
            $history         = $this->engine->appendNarratorTurn([], $result['response']);
            $memory          = $this->engine->appendMemory(null, $result['session_memory_update']);
            $symbolicMemory  = $this->engine->appendMemory(null, $result['symbolic_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'alignment_scaffold'   => $alignmentScaffold,
                'session_memory'       => $memory,
                'symbolic_memory'      => $symbolicMemory,
                'is_climactic_choice'  => (bool) $result['is_climactic_choice'],
                'defining_choice_id'   => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : null,
                'defining_choice_line' => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : null,
                'session_complete'     => $result['session_complete'],
                'turn_count'           => 1,
            ]);

            Log::channel('narration')->info('chaos.start', [
                'session_id'       => $chaosSession->id,
                'story_slug'       => $storySlug,
                'session_number'   => 1,
                'model'            => $model,
                'temperature'      => $temperature,
                'response_bytes'   => strlen($result['response']),
                'session_complete' => $result['session_complete'],
            ]);

            return response()->json($this->formatResult($chaosSession, $result, $storyConfig));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.start_failed', [
                'session_id' => $chaosSession->id,
                'story_slug' => $storySlug,
                'model'      => $model,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);

            return response()->json(['error' => 'The narration engine is currently unavailable. Please try again.'], 500);
        }
    }

    public function turn(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'    => ['required', 'string', 'ulid'],
            'player_action' => ['required', 'string', 'min:1', 'max:500'],
            'model'         => ['nullable', 'string', 'in:' . implode(',', $this->engine->allowedModels())],
            'temperature'   => ['nullable', 'numeric', 'min:0.5', 'max:1.5'],
        ]);

        $chaosSession = ChaosSession::query()->findOrFail($request->string('session_id')->toString());

        if ($chaosSession->session_complete) {
            return response()->json([
                'error'            => 'This session is already complete.',
                'session_complete' => true,
            ], 409);
        }

        $playerAction = $request->string('player_action')->toString();
        $model        = $request->string('model', $chaosSession->model)->toString();
        $temperature  = (float) $request->input('temperature', $this->engine->defaultTemperatureFor($model));

        $story = Story::query()
            ->where('id', $chaosSession->story_id)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null) {
            return response()->json(['error' => 'Story not found.'], 404);
        }

        $storyConfig = ChaosStoryConfig::find($story->slug);
        if ($storyConfig === null) {
            return response()->json(['error' => 'Story no longer available in chaos mode.'], 410);
        }

        $sessionContext = $this->engine->loadSessionContext(
            story:          $story,
            sessionNumber:  (int) $chaosSession->story_session_number,
            openingHandoff: null,
        );

        if ($sessionContext === null) {
            return response()->json([
                'error' => 'This session has not been re-adapted under V2 yet. Re-run the adaptation pipeline before continuing.',
            ], 422);
        }

        $worldState      = $chaosSession->world_state ?? $this->engine->emptyWorldState();
        $alignmentScaffold = $chaosSession->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold();
        $history         = (array) ($chaosSession->conversation_history ?? []);

        $sentHistory = array_slice($history, -20);

        $systemPrompt = $this->engine->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $worldState,
            alignmentScaffold:   $alignmentScaffold,
            symbolicMemory:      $chaosSession->symbolic_memory,
            isSessionStart:      false,
            isClimacticPrevious: (bool) $chaosSession->is_climactic_choice,
        );

        try {
            $result = $this->engine->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: $sentHistory,
                playerAction:        $playerAction,
                protagonist:         $storyConfig['protagonist'],
                temperature:         $temperature,
            );

            $worldState      = $this->engine->mergeStateDelta($worldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($alignmentScaffold, $result['alignment_tally_delta']);
            $history         = $this->engine->appendPlayerTurn($history, $playerAction, $storyConfig['protagonist']);
            $history         = $this->engine->appendNarratorTurn($history, $result['response']);
            $memory          = $this->engine->appendMemory($chaosSession->session_memory, $result['session_memory_update']);
            $symbolicMemory  = $this->engine->appendMemory($chaosSession->symbolic_memory, $result['symbolic_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'alignment_scaffold'   => $alignmentScaffold,
                'session_memory'       => $memory,
                'symbolic_memory'      => $symbolicMemory,
                'is_climactic_choice'  => (bool) $result['is_climactic_choice'],
                'defining_choice_id'   => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : $chaosSession->defining_choice_id,
                'defining_choice_line' => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : $chaosSession->defining_choice_line,
                'session_complete'     => $result['session_complete'],
                'turn_count'           => $chaosSession->turn_count + 1,
                'model'                => $model,
            ]);

            Log::channel('narration')->info('chaos.turn', [
                'session_id'       => $chaosSession->id,
                'story_slug'       => $story->slug,
                'session_number'   => (int) $chaosSession->story_session_number,
                'model'            => $model,
                'temperature'      => $temperature,
                'turn'             => $chaosSession->turn_count,
                'session_complete' => $result['session_complete'],
                'is_climactic'     => (bool) $result['is_climactic_choice'],
                'player_action'    => mb_substr($playerAction, 0, 80),
                'memory_update'    => mb_substr($result['session_memory_update'], 0, 120),
            ]);

            return response()->json($this->formatResult($chaosSession, $result, $storyConfig));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.turn_failed', [
                'session_id'    => $chaosSession->id,
                'model'         => $model,
                'exception'     => $e::class,
                'message'       => $e->getMessage(),
                'player_action' => mb_substr($playerAction, 0, 80),
            ]);

            return response()->json(['error' => 'The narration hiccuped. Your action was preserved — please try again.'], 500);
        }
    }

    public function continueSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_id'  => ['required', 'string', 'ulid'],
            'model'       => ['nullable', 'string', 'in:' . implode(',', $this->engine->allowedModels())],
            'temperature' => ['nullable', 'numeric', 'min:0.5', 'max:1.5'],
        ]);

        $previous = ChaosSession::query()->findOrFail($request->string('session_id')->toString());

        if (! $previous->session_complete) {
            return response()->json(['error' => 'This session is not complete yet.'], 409);
        }

        $story = Story::query()
            ->where('id', $previous->story_id)
            ->with(['adaptation', 'adaptation.sessionAdaptations'])
            ->first();

        if ($story === null) {
            return response()->json(['error' => 'Story not found.'], 404);
        }

        $storyConfig = ChaosStoryConfig::find($story->slug);
        if ($storyConfig === null) {
            return response()->json(['error' => 'Story no longer available in chaos mode.'], 410);
        }

        $nextSessionNumber = (int) $previous->story_session_number + 1;
        $totalSessions     = (int) $story->adaptation->sessionAdaptations->count();

        if ($nextSessionNumber > $totalSessions) {
            return response()->json([
                'error'      => 'This story has no more sessions to play.',
                'story_done' => true,
            ], 410);
        }

        $arcRow         = $this->findArcProgressionRow($story->adaptation, $nextSessionNumber);
        $openingHandoff = (string) ($arcRow['opens_with'] ?? '');

        $sessionContext = $this->engine->loadSessionContext($story, $nextSessionNumber, $openingHandoff);

        if ($sessionContext === null) {
            return response()->json([
                'error' => 'The next session has not been re-adapted under V2 yet. Re-run the adaptation pipeline before continuing.',
            ], 422);
        }

        if (empty($sessionContext['full_session_events'])) {
            return response()->json(['error' => 'The next session has no events to narrate.'], 422);
        }

        $model       = $request->string('model', $previous->model)->toString();
        $temperature = (float) $request->input('temperature', $this->engine->defaultTemperatureFor($model));

        $carriedWorldState   = $previous->world_state ?? $this->engine->emptyWorldState();
        $carriedAlignment    = $previous->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold();
        $carriedSymbolicMem  = $previous->symbolic_memory;

        $chaosSession = ChaosSession::create([
            'story_id'             => $story->id,
            'user_id'              => Auth::id(),
            'story_session_number' => $nextSessionNumber,
            'model'                => $model,
            'conversation_history' => [],
            'world_state'          => $carriedWorldState,
            'alignment_scaffold'   => $carriedAlignment,
            'session_memory'       => $previous->session_memory,
            'symbolic_memory'      => $carriedSymbolicMem,
            'is_climactic_choice'  => false,
            'session_complete'     => false,
            'turn_count'           => 0,
            'ip_address'           => $request->ip(),
        ]);

        $systemPrompt = $this->engine->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $carriedWorldState,
            alignmentScaffold:   $carriedAlignment,
            symbolicMemory:      $carriedSymbolicMem,
            isSessionStart:      true,
            isClimacticPrevious: false,
            isEpisodeTransition: true,
        );

        try {
            $result = $this->engine->callAgent(
                model:               $model,
                systemPrompt:        $systemPrompt,
                conversationHistory: [],
                playerAction:        null,
                protagonist:         $storyConfig['protagonist'],
                temperature:         $temperature,
            );

            $worldState      = $this->engine->mergeStateDelta($carriedWorldState, $result['state_delta']);
            $alignmentScaffold = $this->engine->mergeAlignmentDelta($carriedAlignment, $result['alignment_tally_delta']);
            $history         = $this->engine->appendNarratorTurn([], $result['response']);
            $memory          = $this->engine->appendMemory($previous->session_memory, $result['session_memory_update']);
            $symbolicMemory  = $this->engine->appendMemory($carriedSymbolicMem, $result['symbolic_memory_update']);

            $chaosSession->update([
                'conversation_history' => $history,
                'world_state'          => $worldState,
                'alignment_scaffold'   => $alignmentScaffold,
                'session_memory'       => $memory,
                'symbolic_memory'      => $symbolicMemory,
                'is_climactic_choice'  => (bool) $result['is_climactic_choice'],
                'defining_choice_id'   => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : null,
                'defining_choice_line' => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : null,
                'session_complete'     => $result['session_complete'],
                'turn_count'           => 1,
            ]);

            Log::channel('narration')->info('chaos.continue', [
                'previous_session_id' => $previous->id,
                'session_id'          => $chaosSession->id,
                'story_slug'          => $story->slug,
                'session_number'      => $nextSessionNumber,
                'model'               => $model,
                'session_complete'    => $result['session_complete'],
            ]);

            return response()->json($this->formatResult($chaosSession, $result, $storyConfig));
        } catch (Throwable $e) {
            Log::channel('narration')->error('chaos.continue_failed', [
                'session_id' => $chaosSession->id,
                'model'      => $model,
                'exception'  => $e::class,
                'message'    => $e->getMessage(),
            ]);

            return response()->json(['error' => 'The narration engine could not open the next session. Please try again.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // HTTP-specific helpers (not shared with engine)
    // -------------------------------------------------------------------------

    /**
     * @param  array{response:string, choices:array<int,string>, session_complete:bool, state_delta:array<string,mixed>, alignment_tally_delta:array{chaotic:int,lawful:int,neutral:int}, is_climactic_choice:bool, defining_choice_id:string, defining_choice_line:string, symbolic_memory_update:string, session_memory_update:string}  $result
     * @param  array{slug:string, title:string, protagonist:string, tagline:string, tts_voice_id:string|null}  $storyConfig
     * @return array<string, mixed>
     */
    private function formatResult(ChaosSession $chaosSession, array $result, array $storyConfig): array
    {
        $totalSessions = 0;
        if ($chaosSession->story_id !== null) {
            $story         = Story::query()->where('id', $chaosSession->story_id)->with('adaptation.sessionAdaptations')->first();
            $totalSessions = (int) ($story?->adaptation?->sessionAdaptations?->count() ?? 0);
        }

        $sessionNumber = (int) $chaosSession->story_session_number;
        $hasNext       = $totalSessions > 0 && $sessionNumber < $totalSessions;

        return [
            'session_id'             => $chaosSession->id,
            'story_slug'             => $storyConfig['slug'],
            'story_title'            => $storyConfig['title'],
            'protagonist'            => $storyConfig['protagonist'],
            'session_number'         => $sessionNumber,
            'total_sessions'         => $totalSessions,
            'has_next_session'       => $hasNext,
            'response'               => $result['response'],
            'choices'                => $result['choices'],
            'session_complete'       => $result['session_complete'],
            'world_state'            => $chaosSession->world_state,
            'symbolic_memory'        => $chaosSession->symbolic_memory,
            'defining_choice_id'     => $chaosSession->defining_choice_id,
            'defining_choice_line'   => $chaosSession->defining_choice_line,
            'is_climactic_choice'    => (bool) $chaosSession->is_climactic_choice,
            'session_memory_update'  => $result['session_memory_update'],
            'symbolic_memory_update' => $result['symbolic_memory_update'],
            'turn_count'             => $chaosSession->turn_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findArcProgressionRow(mixed $adaptation, int $sessionNumber): array
    {
        $storyMap = (array) ($adaptation?->story_session_map ?? []);
        foreach ((array) ($storyMap['arc_progression'] ?? []) as $row) {
            if ((int) ($row['session_number'] ?? 0) === $sessionNumber) {
                return (array) $row;
            }
        }

        return [];
    }
}
