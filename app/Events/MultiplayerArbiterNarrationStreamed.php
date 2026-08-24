<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Prompt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MultiplayerArbiterNarrationStreamed implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const string PHASE_DONE = 'done';

    public const string PHASE_DELTA = 'delta';

    public const string PHASE_ERROR = 'error';

    public const string PHASE_READY = 'ready';

    public function __construct(
        public string $lobbyId,
        public string $sourcePromptId,
        public string $phase,
        public ?string $text = null,
        public ?string $sourcePromptValue = null,
        public ?Prompt $prompt = null,
        public ?string $message = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'multiplayer.arbiter.narration.streamed';
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel('multiplayer.lobby.'.$this->lobbyId)];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'lobby_id' => $this->lobbyId,
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
            $payload['prompt'] = [
                'id' => (string) $this->prompt->id,
                'game_id' => (string) $this->prompt->game_id,
                'session_number' => $this->prompt->session_number,
                'prompt' => $this->prompt->prompt ?? '',
                'response' => (string) $this->prompt->response,
                'choices' => [],
                'created_at' => $this->prompt->created_at?->toISOString(),
                'updated_at' => $this->prompt->updated_at?->toISOString(),
            ];
        }

        return $payload;
    }
}
