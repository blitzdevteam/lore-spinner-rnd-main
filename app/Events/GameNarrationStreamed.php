<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Game;
use App\Models\Prompt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live narration for a single-player turn, delivered over the player's private
 * game channel instead of the HTTP request that submitted the move. The request
 * returns as soon as the move is accepted, so no proxy or FastCGI idle timeout
 * can sit between the model and the player.
 *
 * Same phase contract as the multiplayer Arbiter stream: ready → delta* → done | error.
 * `done` carries the committed narration row plus the slice of game state the play
 * page needs, so the client never has to refresh the page to learn the turn landed.
 */
final class GameNarrationStreamed implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const string PHASE_READY = 'ready';

    public const string PHASE_DELTA = 'delta';

    public const string PHASE_DONE = 'done';

    public const string PHASE_ERROR = 'error';

    public function __construct(
        public string $gameId,
        public string $sourcePromptId,
        public string $phase,
        public ?string $text = null,
        public ?string $sourcePromptValue = null,
        public ?Prompt $prompt = null,
        public ?Game $game = null,
        public ?string $message = null,
    ) {}

    public static function channelName(string $gameId): string
    {
        return 'game.'.$gameId;
    }

    /**
     * Mirrors PromptResource without needing a request instance inside a broadcast.
     *
     * @return array<string, mixed>
     */
    public static function promptPayload(Prompt $prompt): array
    {
        return [
            'id' => (string) $prompt->id,
            'game_id' => (string) $prompt->game_id,
            'session_number' => $prompt->session_number,
            'prompt' => $prompt->prompt ?? '',
            'response' => (string) $prompt->response,
            'choices' => collect((array) ($prompt->choices ?? []))
                ->map(static fn ($choice) => is_array($choice)
                    ? [
                        'text' => (string) ($choice['text'] ?? ''),
                        'canonical_choice_id' => $choice['canonical_choice_id'] ?? null,
                        'canonical_path_label' => $choice['canonical_path_label'] ?? null,
                    ]
                    : (string) $choice)
                ->values()
                ->all(),
            'created_at' => $prompt->created_at?->toISOString(),
            'updated_at' => $prompt->updated_at?->toISOString(),
        ];
    }

    /**
     * The game fields the play page reads after a turn. Kept deliberately small:
     * this replaces the post-turn page refresh, so it must never be a reason to
     * ship the whole game resource over the socket.
     *
     * @return array{current_session_number:int|null,current_session_complete:bool,uses_playable_moments:bool}
     */
    public static function gamePayload(Game $game): array
    {
        return [
            'current_session_number' => $game->current_session_number,
            'current_session_complete' => (bool) $game->current_session_complete,
            'uses_playable_moments' => $game->current_playable_moment_id !== null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.narration.streamed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->gameId))];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'game_id' => $this->gameId,
            'source_prompt_id' => $this->sourcePromptId,
            'phase' => $this->phase,
        ];

        if ($this->text !== null) {
            $payload['text'] = $this->text;
        }

        if ($this->sourcePromptValue !== null) {
            $payload['source_prompt_value'] = $this->sourcePromptValue;
        }

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        if ($this->prompt !== null) {
            $payload['prompt'] = self::promptPayload($this->prompt);
        }

        if ($this->game !== null) {
            $payload['game'] = self::gamePayload($this->game);
        }

        return $payload;
    }
}
