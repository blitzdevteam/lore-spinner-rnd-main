<?php

declare(strict_types=1);

namespace App\Actions\Multiplayer;

use App\Events\MultiplayerLobbyChatMessageSent;
use App\Exceptions\Multiplayer\MultiplayerLobbyStateException;
use App\Models\MultiplayerLobby;
use App\Models\MultiplayerLobbyChatMessage;
use App\Models\MultiplayerLobbyMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SendMultiplayerLobbyChatMessageAction
{
    public function handle(User $user, MultiplayerLobby $lobby, string $body): MultiplayerLobbyChatMessage
    {
        $message = DB::transaction(function () use ($user, $lobby, $body): MultiplayerLobbyChatMessage {
            $lockedLobby = MultiplayerLobby::query()
                ->lockForUpdate()
                ->findOrFail($lobby->getKey());
            $member = $lockedLobby->members()
                ->whereBelongsTo($user)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if (! $member instanceof MultiplayerLobbyMember) {
                throw MultiplayerLobbyStateException::mustRejoinBeforeChatting();
            }

            $lockedLobby->update(['last_activity_at' => now()]);

            return $lockedLobby->chatMessages()->create([
                'user_id' => $user->id,
                'body' => $body,
            ]);
        });

        $message->load('user');

        MultiplayerLobbyChatMessageSent::dispatch($message);

        return $message;
    }
}
