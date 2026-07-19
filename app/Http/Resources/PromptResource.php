<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Prompt;
use Illuminate\Http\Request;

/**
 * @mixin Prompt
 */
class PromptResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'game_id'        => $this->game_id,
            'session_number' => $this->session_number,
            'prompt'         => $this->prompt ?? '',
            'response'       => $this->response,
            'choices'        => collect((array) ($this->choices ?? []))
                ->map(static fn ($choice) => is_array($choice)
                    ? [
                        'text' => (string) ($choice['text'] ?? ''),
                        'canonical_choice_id' => $choice['canonical_choice_id'] ?? null,
                        'canonical_path_label' => $choice['canonical_path_label'] ?? null,
                    ]
                    : (string) $choice)
                ->values()
                ->all(),

            // Relations
            'game' => GameResource::make($this->whenLoaded('game')),
        ];
    }
}
