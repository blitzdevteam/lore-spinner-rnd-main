<?php

declare(strict_types=1);

namespace App\Http\Controllers\Discord;

use App\Discord\InteractionSignature;
use App\Discord\SoloTestStory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InteractionController extends Controller
{
    private const int PING = 1;

    private const int APPLICATION_COMMAND = 2;

    private const int MESSAGE_COMPONENT = 3;

    public function __invoke(
        Request $request,
        InteractionSignature $signature,
        SoloTestStory $story,
    ): JsonResponse {
        if (! $signature->valid($request)) {
            return response()->json(['message' => 'Invalid request signature.'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $type = (int) ($payload['type'] ?? 0);

        if ($type === self::PING) {
            return response()->json(['type' => self::PING]);
        }

        $applicationId = (string) config('discord.application_id');

        if ($applicationId !== '' && (string) ($payload['application_id'] ?? '') !== $applicationId) {
            return response()->json(['message' => 'Unexpected Discord application.'], 401);
        }

        if ($type === self::APPLICATION_COMMAND && data_get($payload, 'data.name') === SoloTestStory::COMMAND) {
            return response()->json($story->opening());
        }

        if ($type === self::MESSAGE_COMPONENT) {
            return response()->json($story->choose((string) data_get($payload, 'data.custom_id')));
        }

        return response()->json([
            'type' => 4,
            'data' => [
                'content' => 'This test only answers /play.',
                'flags' => 64,
            ],
        ]);
    }
}
