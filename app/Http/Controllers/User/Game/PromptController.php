<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\Prompt\StorePromptRequest;
use App\Models\Game;
use App\Models\User;
use App\Models\UserActivityDay;
use App\Services\ChaosEngineService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PromptController extends Controller
{
    private const CONTINUE_MARKER = '__continue__';

    public function __construct(private readonly ChaosEngineService $engine) {}

    public function store(
        #[CurrentUser] User $user,
        Game $game,
        StorePromptRequest $request,
    ): RedirectResponse {
        $prompt    = $request->string('prompt')->toString();
        $isContinue = $prompt === self::CONTINUE_MARKER;

        // Record the player's choice on the previous (unanswered) prompt
        $game->prompts()->latest()->first()?->update([
            'prompt' => $isContinue ? self::CONTINUE_MARKER : $prompt,
        ]);

        // Load story + adaptation (needed for session context)
        $story = $game->story()->with(['adaptation', 'adaptation.sessionAdaptations'])->first();

        // Resolve the consequence path for the player's last defining choice so the
        // narrator knows how to echo and honour it this session.
        $resolvedConsequencePath = null;
        if (! empty($game->defining_choice_id) && ! empty($game->defining_choice_line)) {
            $priorSessionNumber = $this->engine->extractSessionNumber($game->defining_choice_id);
            $priorSA = $story->adaptation?->sessionAdaptations
                ?->firstWhere('session_number', $priorSessionNumber);
            if ($priorSA && ! empty($priorSA->choice_consequence_map)) {
                $resolvedConsequencePath = $this->engine->resolveConsequencePath(
                    $game->defining_choice_id,
                    $game->defining_choice_line,
                    $priorSA->choice_consequence_map,
                );
            }
        }

        // Load the authored close design for the current session.
        // Injected into the prompt only when is_climactic_choice was set on the previous
        // turn (i.e. this is the aftermath/close turn).
        $currentSA = $story->adaptation?->sessionAdaptations
            ?->firstWhere('session_number', (int) ($game->current_session_number ?? 1));
        $sessionCloseDesign = $currentSA?->session_close_design;

        $sessionContext = $this->engine->loadSessionContext(
            story:                   $story,
            sessionNumber:           (int) ($game->current_session_number ?? 1),
            openingHandoff:          null,
            resolvedConsequencePath: $resolvedConsequencePath,
            sessionCloseDesign:      $sessionCloseDesign,
        );

        if ($sessionContext === null) {
            return back()->with('error', 'This story session has not been adapted yet. Re-run the adaptation pipeline.');
        }

        // Build conversation history from persisted prompts (last 12 turns for context budget)
        $history = $this->buildConversationHistory($game, $story);

        $worldState        = $game->world_state ?? $this->engine->emptyWorldState();
        $alignmentScaffold = $game->alignment_scaffold ?? $this->engine->emptyAlignmentScaffold();

        // is_climactic_choice on the game record is the value set by the PREVIOUS turn.
        // When true, this is the aftermath/close turn — inject the authored session close.
        $isClimacticThisTurn = (bool) $game->is_climactic_choice;

        $systemPrompt = $this->engine->renderSystemPrompt(
            sessionContext:      $sessionContext,
            worldState:          $worldState,
            alignmentScaffold:   $alignmentScaffold,
            symbolicMemory:      $game->symbolic_memory,
            isSessionStart:      false,
            isClimacticPrevious: $isClimacticThisTurn,
            isClimacticThisTurn: $isClimacticThisTurn,
        );

        // Continue button = autopilot signal; send as player action text
        $playerAction = $isContinue
            ? 'Continue the story forward.'
            : $prompt;

        $protagonist = (string) ($story->system_prompt['character_name'] ?? 'the protagonist');

        try {
            $result = $this->engine->callAgent(
                model:               $game->model,
                systemPrompt:        $systemPrompt,
                conversationHistory: array_slice($history, -12),
                playerAction:        $playerAction,
                protagonist:         $protagonist,
                temperature:         $this->engine->gameTemperatureFor($game->model),
            );

            $newWorldState   = $this->engine->mergeStateDelta($worldState, $result['state_delta']);
            $newAlignment    = $this->engine->mergeAlignmentDelta($alignmentScaffold, $result['alignment_tally_delta']);
            $newSymbolicMem  = $this->engine->appendMemory($game->symbolic_memory, $result['symbolic_memory_update']);

            $game->update([
                'world_state'              => $newWorldState,
                'alignment_scaffold'       => $newAlignment,
                'symbolic_memory'          => $newSymbolicMem,
                'is_climactic_choice'      => (bool) $result['is_climactic_choice'],
                'defining_choice_id'       => $result['defining_choice_id'] !== '' ? $result['defining_choice_id'] : $game->defining_choice_id,
                'defining_choice_line'     => $result['defining_choice_line'] !== '' ? $result['defining_choice_line'] : $game->defining_choice_line,
                'current_session_complete' => $result['session_complete'],
            ]);

        $game->prompts()->create([
                'session_number' => $game->current_session_number,
                'response'       => $result['response'],
                'choices'        => $result['choices'],
            ]);

            UserActivityDay::record($user->id);

            Log::channel('narration')->info('game.turn', [
                'game_id'          => $game->id,
                'story_id'         => $story->id,
                'session_number'   => $game->current_session_number,
                'model'            => $game->model,
                'is_continue'      => $isContinue,
                'is_climactic'     => (bool) $result['is_climactic_choice'],
                'session_complete' => $result['session_complete'],
                'player_input'     => mb_substr($playerAction, 0, 120),
                'response_bytes'   => strlen($result['response']),
            ]);

            return back();
        } catch (Throwable $e) {
            Log::channel('narration')->error('game.turn_failed', [
                'game_id'      => $game->id,
                'model'        => $game->model,
                'exception'    => $e::class,
                'message'      => $e->getMessage(),
                'player_input' => mb_substr($playerAction, 0, 120),
            ]);

            return back()->with('error', 'Narration hiccuped — your input was preserved. Please retry.');
        }
    }

    /**
     * Build a conversation history array from persisted prompts for use as
     * Chaos engine context. Kept as [{role, text}] pairs matching the
     * turn-prompt blade template expectations.
     *
     * @return array<int, array{role:string, text:string}>
     */
    private function buildConversationHistory(Game $game, mixed $story): array
    {
        $history = [];
        $protagonist = (string) ($story->system_prompt['character_name'] ?? 'the protagonist');

        $prompts = $game->prompts()
            ->where('session_number', $game->current_session_number)
            ->oldest()
            ->get();

        foreach ($prompts as $p) {
            if ($p->response) {
                $history[] = [
                    'role' => 'narrator',
                    'text' => $this->engine->stripHtml($p->response),
                ];
            }
            if ($p->prompt && $p->prompt !== self::CONTINUE_MARKER) {
                $history[] = ['role' => 'player', 'text' => $p->prompt, 'protagonist' => $protagonist];
            } elseif ($p->prompt === self::CONTINUE_MARKER) {
                $history[] = ['role' => 'player', 'text' => 'Continue the story forward.', 'protagonist' => $protagonist];
            }
        }

        return $history;
    }
}
