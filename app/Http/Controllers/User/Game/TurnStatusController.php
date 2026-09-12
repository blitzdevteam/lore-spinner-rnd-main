<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Actions\Game\ProcessGameTurnAction;
use App\Events\GameNarrationStreamed;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromptResource;
use App\Models\Game;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The authoritative answer to "did my move land?". The play page polls this
 * while a turn is in flight so a dropped websocket, a closed tab, or a proxy
 * timeout can never leave the player guessing: if the narration row exists the
 * turn is committed, and it is returned here in full.
 */
final class TurnStatusController extends Controller
{
    public function __construct(
        private readonly ProcessGameTurnAction $processGameTurn,
    ) {}

    public function __invoke(
        #[CurrentUser] User $user,
        Game $game,
        Request $request,
    ): JsonResponse {
        abort_unless((int) $game->user_id === (int) $user->id, 403);

        $sourcePromptId = $request->string('source_prompt_id')->toString();
        $pendingSourcePromptId = Cache::get($this->processGameTurn->pendingKey((string) $game->id));

        $payload = [
            'pending' => $pendingSourcePromptId !== null,
            'pending_source_prompt_id' => $pendingSourcePromptId !== null ? (string) $pendingSourcePromptId : null,
            'error' => Cache::get($this->processGameTurn->errorKey((string) $game->id)),
            'source_prompt_id' => $sourcePromptId !== '' ? $sourcePromptId : null,
            'source_prompt_value' => null,
            'prompt' => null,
            'game' => GameNarrationStreamed::gamePayload($game),
        ];

        if ($sourcePromptId === '') {
            return response()->json($payload);
        }

        $responsePrompt = $game->prompts()
            ->where('source_prompt_id', $sourcePromptId)
            ->orderByDesc('id')
            ->first();

        if ($responsePrompt !== null) {
            $payload['source_prompt_value'] = (string) $game->prompts()->whereKey($sourcePromptId)->value('prompt');
            $payload['prompt'] = (new PromptResource($responsePrompt))->resolve($request);
        }

        return response()->json($payload);
    }
}
