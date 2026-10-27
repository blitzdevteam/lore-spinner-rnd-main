<?php

declare(strict_types=1);

namespace App\Http\Controllers\Discord;

use App\Discord\SoloTestStory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ActivityController extends Controller
{
    public function __invoke(Request $request, SoloTestStory $story): Response
    {
        $gameId = $this->gameId($request);
        $testMode = $request->boolean('test');

        return response()
            ->view('discord.activity', [
                'applicationId' => (string) config('discord.application_id'),
                'scenes' => $story->scenes(),
                'testMode' => $testMode,
                'beginUrl' => $testMode
                    ? route('discord.activity.begin', ['test' => 1], false)
                    : ($gameId === null
                        ? null
                        : route('user.games.begin', ['game' => $gameId], false)),
            ])
            ->header(
                'Content-Security-Policy',
                "frame-ancestors 'self' https://discord.com https://*.discord.com https://*.discordsays.com https://*.discordapp.com",
            );
    }

    public function begin(Request $request, SoloTestStory $story): JsonResponse
    {
        if (! $request->boolean('test')) {
            abort(404);
        }

        $scene = $story->scenes()['gate'];

        return response()->json([
            'test' => true,
            'scene' => 'gate',
            'text' => $scene['text'],
            'choices' => $scene['choices'],
        ]);
    }

    private function gameId(Request $request): ?string
    {
        $gameId = $request->string('game')->toString();

        if (preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $gameId) !== 1) {
            return null;
        }

        return $gameId;
    }
}
