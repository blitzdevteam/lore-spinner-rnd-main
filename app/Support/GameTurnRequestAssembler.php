<?php

declare(strict_types=1);

namespace App\Support;

use App\ChaosMode\ChaosStoryConfig;
use App\Models\Game;
use App\Models\PlayerTokenUsage;
use App\Models\Prompt;
use App\Models\Story;
use App\Services\ChaosEngineService;
use RuntimeException;

/**
 * Reconstructs the narration LLM request for a committed turn using the same
 * history ordering rules as ProcessGameTurnAction / ProcessGameOpeningAction.
 */
final class GameTurnRequestAssembler
{
    private const CONTINUE_MARKER = '__continue__';

    public function __construct(private readonly ChaosEngineService $engine) {}

    /**
     * @return array<string, mixed>
     */
    public function assemble(Game $game, Prompt $responsePrompt): array
    {
        $usage = PlayerTokenUsage::query()
            ->where('prompt_reference_id', $responsePrompt->id)
            ->first();

        if ($usage === null) {
            throw new RuntimeException("No player_token_usages row for response prompt {$responsePrompt->id}.");
        }

        $story = $game->story ?? Story::query()->findOrFail($game->story_id);
        $protagonist = ChaosStoryConfig::protagonistFor(
            (string) $story->slug,
            $story->system_prompt['character_name'] ?? null,
        );
        $protagonistUpper = mb_strtoupper($protagonist);

        $isOpening = $usage->kind === PlayerTokenUsage::KIND_BEGIN
            || $usage->kind === PlayerTokenUsage::KIND_NEXT_SESSION;

        $sourcePrompt = $responsePrompt->source_prompt_id !== null
            ? Prompt::query()->find($responsePrompt->source_prompt_id)
            : null;

        $storedPlayerInput = $isOpening ? null : (string) ($sourcePrompt?->prompt ?? '');
        $llmPlayerAction = $this->llmPlayerAction($storedPlayerInput, $isOpening);

        $fullHistory = $this->buildHistoryBeforeTurn($game, $story, $responsePrompt, $sourcePrompt, $isOpening);
        $turnCountInMoment = $this->turnCountInMomentAtCall($game, $responsePrompt);
        $boilerActive = $game->current_playable_moment_id !== null
            || $responsePrompt->moment_key !== null;
        $historyWindow = $isOpening
            ? []
            : array_slice(
                $fullHistory,
                -max(20, ($turnCountInMoment + 1) * 2),
            );

        $messages = $this->formatPrismMessages(
            $historyWindow,
            $llmPlayerAction,
            $protagonist,
            $protagonistUpper,
            $isOpening,
        );

        $systemStored = (string) ($usage->system_prompt ?? '');
        $systemWithSparseDelta = $systemStored !== ''
            ? $this->engine->withSparseStateDeltaContract($systemStored)
            : '';

        return [
            'exported_at' => now()->toIso8601String(),
            'game_id' => $game->id,
            'story_slug' => $story->slug,
            'response_prompt_id' => $responsePrompt->id,
            'source_prompt_id' => $responsePrompt->source_prompt_id,
            'response_created_at' => (string) $responsePrompt->created_at,
            'session_number' => $responsePrompt->session_number,
            'moment_key_at_commit' => $responsePrompt->moment_key,
            'usage_kind' => $usage->kind,
            'model' => $usage->model,
            'temperature' => ChaosEngineService::GAME_TEMPERATURE,
            'is_opening_turn' => $isOpening,
            'stored_player_input' => $storedPlayerInput,
            'llm_player_action' => $llmPlayerAction,
            'turn_count_in_moment_at_call' => $turnCountInMoment,
            'boiler_active_at_export' => $boilerActive,
            'conversation_history_full_before_slice' => $fullHistory,
            'conversation_history_window' => $historyWindow,
            'prism_messages' => $messages,
            'system_prompt_stored' => $systemStored,
            'system_prompt_with_sparse_delta_contract' => $systemWithSparseDelta,
            'notes' => [
                'system_prompt_stored matches ProcessGameTurnAction before callAgent (includes CONTINUE / REPEATED INTENT when applicable).',
                'sparse_state_delta_contract is added inside callAgent only (above the live turn context when present); included in system_prompt_with_sparse_delta_contract.',
                'history excludes the current turn player line; it lives in the final user message via llm_player_action.',
                'playable_moment_turn_count at call is estimated from prior response rows sharing moment_key.',
                'final-message instructions and the sparse contract come from the code deployed at export time, which may differ from the code that served the turn.',
            ],
            'usage' => [
                'occurred_at' => $usage->occurred_at?->toIso8601String(),
                'raw_usage' => $usage->raw_usage,
                'input_tokens' => $usage->input_tokens,
                'output_tokens' => $usage->output_tokens,
            ],
            'prompt_chain' => $this->promptChain($game, (int) $responsePrompt->session_number),
        ];
    }

    /**
     * @return array<int, Prompt>
     */
    public function responsePromptsInOrder(Game $game, ?int $sessionNumber = null): array
    {
        $query = $game->prompts()->orderBy('id');
        if ($sessionNumber !== null) {
            $query->where('session_number', $sessionNumber);
        }

        return $query->get()
            ->filter(fn (Prompt $prompt): bool => filled($prompt->response))
            ->values()
            ->all();
    }

    private function llmPlayerAction(?string $stored, bool $isOpening): ?string
    {
        if ($isOpening) {
            return null;
        }

        if ($stored === self::CONTINUE_MARKER) {
            return 'Continue the story forward.';
        }

        return $stored !== '' ? $stored : null;
    }

    /**
     * History immediately before the LLM call that produced $responsePrompt.
     *
     * @return array<int, array{role:string, text:string, protagonist?:string, from_prompt_id:string}>
     */
    private function buildHistoryBeforeTurn(
        Game $game,
        Story $story,
        Prompt $responsePrompt,
        ?Prompt $sourcePrompt,
        bool $isOpening,
    ): array {
        if ($isOpening) {
            return [];
        }

        if ($sourcePrompt === null) {
            throw new RuntimeException("Turn response {$responsePrompt->id} has no source prompt.");
        }

        $protagonist = ChaosStoryConfig::protagonistFor(
            (string) $story->slug,
            $story->system_prompt['character_name'] ?? null,
        );

        $history = [];
        $sessionNumber = (int) $responsePrompt->session_number;

        $prompts = $game->prompts()
            ->where('session_number', $sessionNumber)
            ->where('id', '<', $sourcePrompt->id)
            ->orderBy('id')
            ->get();

        foreach ($prompts as $prompt) {
            $history = array_merge($history, $this->historyEntriesForRow($prompt, $protagonist));
        }

        if (filled($sourcePrompt->response)) {
            $history[] = [
                'role' => 'narrator',
                'text' => $this->engine->stripHtml((string) $sourcePrompt->response),
                'from_prompt_id' => $sourcePrompt->id,
            ];
        }

        return $history;
    }

    /**
     * @return array<int, array{role:string, text:string, protagonist?:string, from_prompt_id:string}>
     */
    private function historyEntriesForRow(Prompt $prompt, string $protagonist): array
    {
        $entries = [];

        if (filled($prompt->response)) {
            $entries[] = [
                'role' => 'narrator',
                'text' => $this->engine->stripHtml((string) $prompt->response),
                'from_prompt_id' => $prompt->id,
            ];
        }

        if (filled($prompt->prompt) && $prompt->prompt !== self::CONTINUE_MARKER) {
            $entries[] = [
                'role' => 'player',
                'text' => (string) $prompt->prompt,
                'protagonist' => $protagonist,
                'from_prompt_id' => $prompt->id,
            ];
        } elseif ($prompt->prompt === self::CONTINUE_MARKER) {
            $entries[] = [
                'role' => 'player',
                'text' => 'Continue the story forward.',
                'protagonist' => $protagonist,
                'from_prompt_id' => $prompt->id,
            ];
        }

        return $entries;
    }

    private function turnCountInMomentAtCall(Game $game, Prompt $responsePrompt): int
    {
        if ($responsePrompt->moment_key === null) {
            return 0;
        }

        return $game->prompts()
            ->where('session_number', $responsePrompt->session_number)
            ->where('moment_key', $responsePrompt->moment_key)
            ->whereNotNull('source_prompt_id')
            ->where('id', '<', $responsePrompt->id)
            ->count();
    }

    /**
     * @param  array<int, array{role:string, text:string, protagonist?:string}>  $historyWindow
     * @return array<int, array{role:string, content:string}>
     */
    private function formatPrismMessages(
        array $historyWindow,
        ?string $llmPlayerAction,
        string $protagonist,
        string $protagonistUpper,
        bool $isOpening,
    ): array {
        $messages = [];

        foreach ($historyWindow as $turn) {
            $text = mb_trim((string) ($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            if (($turn['role'] ?? '') === 'narrator') {
                $messages[] = ['role' => 'assistant', 'content' => $text];

                continue;
            }

            $speaker = mb_strtoupper((string) ($turn['protagonist'] ?? $protagonist));
            $messages[] = ['role' => 'user', 'content' => "[{$speaker}]: {$text}"];
        }

        $instructions = $isOpening
            ? $this->engine->openingInstructions()
            : $this->engine->turnInstructions($protagonistUpper);

        $actionBlock = $llmPlayerAction !== null && mb_trim($llmPlayerAction) !== ''
            ? "{$protagonistUpper}'S ACTION: ".mb_trim($llmPlayerAction)."\n\n"
            : '';

        $messages[] = [
            'role' => 'user',
            'content' => $actionBlock.$instructions,
        ];

        return $messages;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function promptChain(Game $game, int $sessionNumber): array
    {
        $rows = [];

        foreach ($game->prompts()->where('session_number', $sessionNumber)->orderBy('id')->get() as $prompt) {
            $rows[] = [
                'id' => $prompt->id,
                'created_at' => (string) $prompt->created_at,
                'source_prompt_id' => $prompt->source_prompt_id,
                'moment_key' => $prompt->moment_key,
                'has_response' => filled($prompt->response),
                'has_player_prompt' => filled($prompt->prompt),
                'player_prompt' => $prompt->prompt,
                'response_preview' => mb_substr(strip_tags((string) $prompt->response), 0, 120),
                'choices_count' => is_array($prompt->choices) ? count($prompt->choices) : 0,
            ];
        }

        return $rows;
    }
}
