<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Actions\Game\ProcessGameTurnAction;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin Game
 */
class GameResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        $turnAction = app(ProcessGameTurnAction::class);

        return [
            'id'                       => $this->id,
            'user_id'                  => $this->user_id,
            'story_id'                 => $this->story_id,
            'current_session_number'   => $this->current_session_number,
            'current_session_complete' => (bool) $this->current_session_complete,
            'uses_playable_moments'    => $this->current_playable_moment_id !== null,
            'total_sessions'           => (int) ($this->story?->adaptation?->session_adaptations_count ?? 0),
            'model'                    => $this->model,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'turn_pending'             => Cache::has($turnAction->pendingKey((string) $this->id)),
            'turn_error'               => Cache::get($turnAction->errorKey((string) $this->id)),

            // Relations
            'story'   => StoryResource::make($this->whenLoaded('story')),
            'user'    => UserResource::make($this->whenLoaded('user')),
            'prompts' => PromptResource::collection($this->whenLoaded('prompts')),

            // Counts
            'prompts_count' => $this->whenCounted('prompts'),

            // Cold-open UX: chat-bar placeholder for the player's very first move.
            // Sourced from session 1 entry_point_diagnosis.freeform_input_hint (pipeline D10).
            // Null when adaptation has not been run or hint is absent (graceful degradation).
            'first_input_hint' => $this->whenLoaded('story', function () {
                $diag = $this->story?->adaptation?->sessionAdaptations?->first()?->entry_point_diagnosis;
                return is_array($diag) ? ($diag['freeform_input_hint'] ?? null) : null;
            }),
        ];
    }
}
