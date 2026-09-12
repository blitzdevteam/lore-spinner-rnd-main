<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Actions\Game\ProcessGameTurnAction;
use App\Actions\Game\RunDeferredGameTurnAction;
use App\Events\GameNarrationStreamed;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Game\Prompt\StorePromptRequest;
use App\Http\Resources\PromptResource;
use App\Models\Game;
use App\Models\Prompt;
use App\Models\User;
use App\Services\ChaosEngineService;
use Illuminate\Cache\Lock;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class PromptController extends Controller
{
    private const CONTINUE_MARKER = '__continue__';

    public function __construct(
        private readonly ChaosEngineService $engine,
        private readonly ProcessGameTurnAction $processGameTurn,
    ) {}

    /**
     * Accepts a player's move and returns before the narrator runs.
     *
     * Three clients share this endpoint:
     *  - The play page (JSON): gets a 202 straight away, then receives the prose
     *    over its private broadcast channel and confirms the commit by polling
     *    {@see TurnStatusController}. Nothing here waits on the model.
     *  - Inertia form posts: same deferred run, answered with a redirect.
     *  - Browsers still running the previous bundle (NDJSON): the model is streamed
     *    inside this request. Kept only so an in-flight deploy cannot strand them.
     */
    public function store(
        #[CurrentUser] User $user,
        Game $game,
        StorePromptRequest $request,
    ): RedirectResponse|StreamedResponse|JsonResponse {
        $promptText = $request->string('prompt')->toString();
        $sourcePromptId = $request->string('prompt_id')->toString();
        $lockName = "game-turn:{$game->id}:{$sourcePromptId}";
        $turnLock = Cache::lock($lockName, 180);
        $wantsJson = $this->wantsJsonTurn($request);

        if (! $turnLock->get()) {
            return $this->rejectTurn($request, $game, $sourcePromptId, 'in_progress', 'That move is already being narrated.');
        }

        $playerAction = $promptText;
        $deferred = false;

        try {
            $sourcePrompt = $game->prompts()
                ->whereKey($sourcePromptId)
                ->whereNull('prompt')
                ->first();

            // Newest by ULID, matching the order the play page renders. created_at
            // ties would let the two sides disagree about which turn is current and
            // reject a legitimate move as out of turn.
            if ($sourcePrompt === null
                || $game->prompts()->orderByDesc('id')->value('id') !== $sourcePrompt->id
                || (int) ($sourcePrompt->session_number ?? 0) !== (int) ($game->current_session_number ?? 0)
            ) {
                return $this->rejectTurn($request, $game, $sourcePromptId, 'out_of_turn', 'That move arrived out of turn.');
            }

            [$storedPromptValue, $playerAction, $explicitBranch] = $this->validateSubmittedAction(
                $request,
                $game,
                $sourcePrompt,
            );

            if ($this->wantsStreamingTurn($request)) {
                Cache::put($this->processGameTurn->pendingKey($game->id), $sourcePromptId, 180);
                Cache::forget($this->processGameTurn->errorKey($game->id));
                $deferred = true;

                return $this->streamTurn(
                    request: $request,
                    game: $game,
                    lock: $turnLock,
                    lockName: $lockName,
                    sourcePromptId: $sourcePromptId,
                    storedPromptValue: $storedPromptValue,
                    playerAction: $playerAction,
                    explicitBranch: $explicitBranch,
                    userId: $user->id,
                );
            }

            // Mark the turn pending before the response leaves so the very first
            // status poll already sees it in flight, then run the narrator after
            // the response: no proxy or FastCGI timeout can sit between the model
            // and the player when the request has already finished.
            Cache::put($this->processGameTurn->pendingKey($game->id), $sourcePromptId, 180);
            Cache::forget($this->processGameTurn->errorKey($game->id));
            $deferred = true;

            $this->dispatchDeferredTurn(
                lockName: $lockName,
                lockOwner: (string) $turnLock->owner(),
                gameId: (string) $game->id,
                sourcePromptId: $sourcePromptId,
                storedPromptValue: $storedPromptValue,
                playerAction: $playerAction,
                explicitBranch: $explicitBranch,
                userId: $user->id,
            );

            if ($wantsJson) {
                return response()->json([
                    'accepted' => true,
                    'game_id' => (string) $game->id,
                    'source_prompt_id' => $sourcePromptId,
                    'source_prompt_value' => $storedPromptValue,
                    'channel' => GameNarrationStreamed::channelName((string) $game->id),
                ], 202);
            }

            return back()->with('narrating', true);
        } catch (Throwable $e) {
            Log::channel('narration')->error('game.turn_failed', [
                'game_id' => $game->id,
                'model' => $game->model,
                'source_prompt_id' => $sourcePromptId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'player_input' => mb_substr($playerAction, 0, 120),
            ]);

            if ($wantsJson) {
                return response()->json([
                    'accepted' => false,
                    'reason' => 'invalid',
                    'message' => 'Narration hiccuped — please retry this turn.',
                ], 422);
            }

            return back()->with('error', 'Narration hiccuped — please retry this turn.');
        } finally {
            if (! $deferred) {
                $turnLock->release();
            }
        }
    }

    /**
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     */
    private function dispatchDeferredTurn(
        string $lockName,
        string $lockOwner,
        string $gameId,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
    ): void {
        dispatch(static function () use (
            $lockName,
            $lockOwner,
            $gameId,
            $sourcePromptId,
            $storedPromptValue,
            $playerAction,
            $explicitBranch,
            $userId,
        ): void {
            $lock = Cache::restoreLock($lockName, $lockOwner);

            try {
                app(RunDeferredGameTurnAction::class)->handle(
                    gameId: $gameId,
                    sourcePromptId: $sourcePromptId,
                    storedPromptValue: $storedPromptValue,
                    playerAction: $playerAction,
                    explicitBranch: $explicitBranch,
                    userId: $userId,
                );
            } finally {
                $lock->release();
            }
        })->afterResponse();
    }

    /**
     * A move the server will not narrate. For the play page this is a JSON 409
     * that says why; when the source prompt has in fact already been answered the
     * committed turn rides along, so a resubmitted move renders the existing
     * narration instead of failing or generating a second one.
     */
    private function rejectTurn(
        StorePromptRequest $request,
        Game $game,
        string $sourcePromptId,
        string $reason,
        string $message,
    ): RedirectResponse|JsonResponse {
        if (! $this->wantsJsonTurn($request)) {
            return to_route('user.games.show', $game);
        }

        $payload = [
            'accepted' => false,
            'reason' => $reason,
            'message' => $message,
        ];

        $existingResponse = $game->prompts()
            ->where('source_prompt_id', $sourcePromptId)
            ->orderByDesc('id')
            ->first();

        if ($existingResponse !== null) {
            $payload['reason'] = 'already_answered';
            $payload['source_prompt_id'] = $sourcePromptId;
            $payload['source_prompt_value'] = (string) $game->prompts()->whereKey($sourcePromptId)->value('prompt');
            $payload['prompt'] = (new PromptResource($existingResponse))->resolve($request);
            $payload['game'] = GameNarrationStreamed::gamePayload($game->fresh() ?? $game);
        }

        return response()->json($payload, 409);
    }

    /**
     * The play page asks for JSON; the NDJSON legacy bundle asks for x-ndjson;
     * Inertia asks for HTML. Only the first gets the 202 contract.
     */
    private function wantsJsonTurn(StorePromptRequest $request): bool
    {
        $accept = (string) $request->headers->get('Accept');

        return str_contains($accept, 'application/json') && ! str_contains($accept, 'application/x-ndjson');
    }

    /**
     * @param  array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null  $explicitBranch
     */
    private function streamTurn(
        Request $request,
        Game $game,
        Lock $lock,
        string $lockName,
        string $sourcePromptId,
        string $storedPromptValue,
        string $playerAction,
        ?array $explicitBranch,
        int $userId,
    ): StreamedResponse {
        return response()->stream(function () use (
            $request,
            $game,
            $lock,
            $lockName,
            $sourcePromptId,
            $storedPromptValue,
            $playerAction,
            $explicitBranch,
            $userId,
        ): void {
            $send = static function (string $event, array $payload = []): void {
                echo json_encode(
                    ['event' => $event, ...$payload],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )."\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $send('ready');

            try {
                $responsePrompt = $this->processGameTurn->handleStreaming(
                    gameId: (string) $game->id,
                    sourcePromptId: $sourcePromptId,
                    storedPromptValue: $storedPromptValue,
                    playerAction: $playerAction,
                    explicitBranch: $explicitBranch,
                    userId: $userId,
                    onResponseDelta: function (string $delta) use ($send): void {
                        $send('delta', ['text' => $delta]);
                    },
                );

                if ($responsePrompt === null) {
                    throw new RuntimeException('The turn could not be processed.');
                }

                $send('done', [
                    'source_prompt_id' => $sourcePromptId,
                    'source_prompt_value' => $storedPromptValue,
                    'prompt' => (new PromptResource($responsePrompt))->resolve($request),
                ]);
            } catch (Throwable $e) {
                Log::channel('narration')->error('game.turn_stream_failed', [
                    'game_id' => $game->id,
                    'source_prompt_id' => $sourcePromptId,
                    'lock_name' => $lockName,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'player_input' => mb_substr($playerAction, 0, 120),
                ]);

                $send('error', [
                    'message' => 'Narration hiccuped — please retry this turn.',
                ]);
            } finally {
                $lock->release();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function wantsStreamingTurn(StorePromptRequest $request): bool
    {
        return str_contains((string) $request->headers->get('Accept'), 'application/x-ndjson')
            || $request->boolean('stream');
    }

    /**
     * @return array{0:string,1:string,2:array{defining_choice_id:string,defining_path_label:string,defining_choice_line:?string}|null}
     */
    private function validateSubmittedAction(
        StorePromptRequest $request,
        Game $game,
        Prompt $sourcePrompt,
    ): array {
        $game->loadMissing(['story.adaptation.sessionAdaptations', 'currentPlayableMoment']);

        $submittedText = $request->string('prompt')->toString();
        $suggestionIndex = $request->input('suggestion_index');

        if ($suggestionIndex === null) {
            // Continue is valid in both modes: it lets the player stay in the scene
            // without being required to invent an action. Boiler treats it as an
            // ordinary turn — the active room still governs what happens next.
            $isContinueMarker = $submittedText === self::CONTINUE_MARKER;

            return [
                $submittedText,
                $isContinueMarker ? 'Continue the story forward.' : $submittedText,
                null,
            ];
        }

        $choices = (array) ($sourcePrompt->choices ?? []);
        $storedChoice = $choices[(int) $suggestionIndex] ?? null;

        if ($storedChoice === null) {
            throw new RuntimeException('The selected suggestion no longer exists on this prompt.');
        }

        if (is_string($storedChoice)) {
            if ($storedChoice !== $submittedText) {
                throw new RuntimeException('The submitted legacy choice does not match the stored prompt.');
            }

            return [$submittedText, $submittedText, null];
        }

        if (! is_array($storedChoice) || ($storedChoice['text'] ?? null) !== $submittedText) {
            throw new RuntimeException('The submitted suggestion does not match the stored prompt.');
        }

        $storedChoiceId = $storedChoice['canonical_choice_id'] ?? null;
        $storedPathLabel = $storedChoice['canonical_path_label'] ?? null;
        if (
            $request->input('canonical_choice_id') !== $storedChoiceId
            || $request->input('canonical_path_label') !== $storedPathLabel
        ) {
            throw new RuntimeException('The submitted canonical identity does not match the stored suggestion.');
        }

        $isContinue = $storedChoiceId === null && strcasecmp($submittedText, 'Continue') === 0;
        if ($storedChoiceId === null) {
            return [
                $isContinue ? self::CONTINUE_MARKER : $submittedText,
                $isContinue ? 'Continue the story forward.' : $submittedText,
                null,
            ];
        }

        $choiceId = (string) $storedChoiceId;
        $pathLabel = mb_strtoupper((string) ($storedPathLabel ?? ''));
        $momentChoiceIds = (array) data_get($game->currentPlayableMoment?->scene_card, 'choice_ids', []);

        if (! in_array($choiceId, $momentChoiceIds, true)) {
            return $this->demoteSubmittedMapping(
                $submittedText,
                $game->id,
                $choiceId,
                $pathLabel,
                'not_bound_to_moment',
            );
        }

        $choiceSession = $this->engine->extractSessionNumber($choiceId);
        $session = $game->story?->adaptation?->sessionAdaptations?->firstWhere('session_number', $choiceSession);
        $design = (array) ($session?->session_choice_design ?? []);

        $branchingChoice = collect((array) ($design['branching_choices'] ?? []))
            ->firstWhere('choice_id', $choiceId);
        if (is_array($branchingChoice)) {
            $option = collect((array) ($branchingChoice['options'] ?? []))
                ->first(fn ($option): bool => mb_strtoupper((string) ($option['label'] ?? '')) === $pathLabel);

            if (! is_array($option)) {
                return $this->demoteSubmittedMapping(
                    $submittedText,
                    $game->id,
                    $choiceId,
                    $pathLabel,
                    'branching_path_not_in_authored_options',
                );
            }

            return [
                $submittedText,
                $submittedText,
                [
                    'defining_choice_id' => $choiceId,
                    'defining_path_label' => $pathLabel,
                    'defining_choice_line' => ($option['defining_line'] ?? '') !== ''
                        ? (string) $option['defining_line']
                        : null,
                ],
            ];
        }

        $emotionalChoice = collect((array) ($design['emotional_choices'] ?? []))
            ->firstWhere('choice_id', $choiceId);
        if (is_array($emotionalChoice)) {
            $option = collect((array) ($emotionalChoice['options'] ?? []))
                ->first(fn ($option): bool => mb_strtoupper((string) ($option['label'] ?? '')) === $pathLabel);

            if (! is_array($option)) {
                return $this->demoteSubmittedMapping(
                    $submittedText,
                    $game->id,
                    $choiceId,
                    $pathLabel,
                    'emotional_path_not_in_authored_options',
                );
            }

            Log::channel('narration')->info('boiler.submitted_emotional_mapping', [
                'game_id' => $game->id,
                'canonical_choice_id' => $choiceId,
                'canonical_path_label' => $pathLabel,
            ]);

            return [$submittedText, $submittedText, null];
        }

        return $this->demoteSubmittedMapping(
            $submittedText,
            $game->id,
            $choiceId,
            $pathLabel,
            'choice_not_in_design',
        );
    }

    /**
     * @return array{0:string,1:string,2:null}
     */
    private function demoteSubmittedMapping(
        string $submittedText,
        string $gameId,
        string $choiceId,
        string $pathLabel,
        string $reason,
    ): array {
        Log::channel('narration')->warning('boiler.submitted_mapping_demoted', [
            'game_id' => $gameId,
            'canonical_choice_id' => $choiceId,
            'canonical_path_label' => $pathLabel,
            'reason' => $reason,
            'player_input' => mb_substr($submittedText, 0, 120),
        ]);

        return [$submittedText, $submittedText, null];
    }
}
