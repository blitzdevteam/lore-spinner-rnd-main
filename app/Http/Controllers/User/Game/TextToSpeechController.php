<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\Prompt;
use App\Models\User;
use App\Services\NarrationAudioService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves narration audio for a prompt.
 *
 * Cache hit  → BinaryFileResponse from disk (full byte-range support, which
 *              iOS Safari needs for duration display and seeking).
 * Cache miss → 202 + a background render. The provider takes far longer than
 *              the gateway allows, so the request never waits on it; the client
 *              re-requests until the mp3 is on disk.
 */
final class TextToSpeechController extends Controller
{
    public function __construct(private readonly NarrationAudioService $audio) {}

    public function __invoke(#[CurrentUser] User $user, Game $game, Prompt $prompt): Response
    {
        abort_unless($prompt->game_id === $game->id, 404);
        abort_unless(filled($prompt->response), 404);
        abort_unless($this->canAccessGameAudio($user, $game), 403);

        if ($this->audio->isReady($prompt)) {
            return $this->serve($prompt);
        }

        $failure = $this->audio->failure($prompt);

        if ($failure !== null) {
            return response()->json(['status' => 'failed', 'message' => $failure], 503);
        }

        if ($this->audio->reserve($prompt)) {
            $this->renderAfterResponse((string) $game->id, (string) $prompt->id);
        }

        return response()->json(['status' => 'generating'], 202)
            ->header('Retry-After', '2')
            ->header('Cache-Control', 'no-store');
    }

    private function renderAfterResponse(string $gameId, string $promptId): void
    {
        dispatch(function () use ($gameId, $promptId): void {
            $game = Game::query()->find($gameId);
            $prompt = Prompt::query()->whereKey($promptId)->first();

            if ($game === null || $prompt === null) {
                return;
            }

            app(NarrationAudioService::class)->generate($game, $prompt);
        })->afterResponse();
    }

    private function canAccessGameAudio(User $user, Game $game): bool
    {
        if ($game->user_id === $user->id) {
            return true;
        }

        return MultiplayerLobby::query()
            ->where('game_id', $game->id)
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    private function serve(Prompt $prompt): BinaryFileResponse
    {
        $response = new BinaryFileResponse(
            Storage::disk('local')->path($this->audio->path($prompt)),
            200,
            [
                'Content-Type' => 'audio/mpeg',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
                'X-Accel-Buffering' => 'no',
            ]
        );

        $response->setAutoLastModified();

        return $response;
    }
}
