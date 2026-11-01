<?php

declare(strict_types=1);

namespace App\Http\Controllers\Discord;

use App\Actions\Discord\SyncDiscordActivityRoomAction;
use App\Discord\SoloTestStory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discord\SyncDiscordActivityRoomRequest;
use App\Models\DiscordActivityRoomMember;
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
                'roomUrl' => $testMode
                    ? route('discord.activity.rooms.sync', ['test' => 1], false)
                    : null,
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

    public function syncRoom(
        SyncDiscordActivityRoomRequest $request,
        SyncDiscordActivityRoomAction $action,
    ): JsonResponse {
        $snapshot = $request->snapshot();
        $room = $action->handle($snapshot);
        $members = $room->members->keyBy('discord_user_id');

        return response()->json([
            'test' => true,
            'room_id' => $room->id,
            'instance_id' => $room->instance_id,
            'participants' => array_map(
                static function (array $participant) use ($members): array {
                    $member = $members->get($participant['id']);

                    return [
                        'id' => $participant['id'],
                        'username' => $member instanceof DiscordActivityRoomMember
                            ? $member->username
                            : $participant['username'],
                        'global_name' => $member instanceof DiscordActivityRoomMember
                            ? $member->global_name
                            : $participant['global_name'],
                    ];
                },
                $snapshot['participants'],
            ),
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
