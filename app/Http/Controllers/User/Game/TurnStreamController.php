<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Services\Runtime\GameTurnStreamStore;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TurnStreamController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        Game $game,
        string $prompt,
        GameTurnStreamStore $store,
    ): StreamedResponse {
        abort_unless((int) $game->user_id === (int) $user->id, 404);

        return response()->stream(function () use ($game, $prompt, $store): void {
            set_time_limit(0);
            $offset = 0;
            $startedAt = microtime(true);

            while (microtime(true) - $startedAt < 180) {
                if (connection_aborted()) {
                    return;
                }

                $state = $store->snapshot((string) $game->id, $prompt);
                $text = (string) ($state['text'] ?? '');
                if (mb_strlen($text) > $offset) {
                    $delta = mb_substr($text, $offset);
                    $offset = mb_strlen($text);
                    $this->emit('chunk', ['text' => $delta]);
                }

                if (($state['error'] ?? null) !== null) {
                    $this->emit('error', ['message' => $state['error']]);

                    return;
                }
                if (($state['final'] ?? false) === true) {
                    $this->emit('final', ['committed' => true]);

                    return;
                }

                $this->emit('heartbeat', ['at' => time()]);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
                usleep(250000);
            }

            $this->emit('error', ['message' => 'Narration stream timed out.']);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
    }
}
