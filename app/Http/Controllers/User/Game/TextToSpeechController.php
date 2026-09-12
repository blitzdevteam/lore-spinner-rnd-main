<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\MultiplayerLobby;
use App\Models\Prompt;
use App\Models\User;
use App\Services\NarrationAudioService;
use App\Tts\TtsVoiceCatalog;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
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

    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        Game $game,
        Prompt $prompt,
    ): Response {
        abort_unless($prompt->game_id === $game->id, 404);
        abort_unless(filled($prompt->response), 404);
        abort_unless($this->canAccessGameAudio($user, $game), 403);

        $game->loadMissing('story');
        $recommendedVoiceId = TtsVoiceCatalog::recommendedForGame($game);
        $voiceId = TtsVoiceCatalog::resolveForGame($request->query('voice'), $game);

        if ($this->audio->isReady($prompt, $voiceId, $recommendedVoiceId)) {
            return $this->serve($prompt, $voiceId, $recommendedVoiceId);
        }

        $failure = $this->audio->failure($prompt, $voiceId, $recommendedVoiceId);

        if ($failure !== null) {
            return response()->json(['status' => 'failed', 'message' => $failure], 503);
        }

        if (! $this->audio->isGenerating($prompt, $voiceId, $recommendedVoiceId)) {
            $this->audio->markPending($prompt, $voiceId, $recommendedVoiceId);
            $this->renderAfterResponse((string) $game->id, (string) $prompt->id, $voiceId);
        }

        return response()->json(['status' => 'generating'], 202)
            ->header('Retry-After', '2')
            ->header('Cache-Control', 'no-store');
    }

    private function renderAfterResponse(string $gameId, string $promptId, string $voiceId): void
    {
        dispatch(function () use ($gameId, $promptId, $voiceId): void {
            $game = Game::query()->find($gameId);
            $prompt = Prompt::query()->whereKey($promptId)->first();

            if ($game === null || $prompt === null) {
                return;
            }

            app(NarrationAudioService::class)->generate($game, $prompt, $voiceId);
        })->afterResponse();
    }

    private function canAccessGameAudio(User $user, Game $game): bool
    {
        $multiplayerLobby = MultiplayerLobby::query()
            ->where('game_id', $game->id)
            ->first();

        if ($multiplayerLobby !== null) {
            return $multiplayerLobby->voiceOverEnabled()
                && $multiplayerLobby->members()
                    ->where('user_id', $user->id)
                    ->exists();
        }

        if ($game->user_id === $user->id) {
            return true;
        }

        return false;
    }

    private function serve(Prompt $prompt, string $voiceId, string $recommendedVoiceId): BinaryFileResponse
    {
        $response = new BinaryFileResponse(
            Storage::disk('local')->path(
                $this->audio->resolveStoragePath($prompt, $voiceId, $recommendedVoiceId),
            ),
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
