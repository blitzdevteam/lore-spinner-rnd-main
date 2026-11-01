<?php

declare(strict_types=1);

namespace App\Actions\Discord;

use App\Models\DiscordActivityRoom;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SyncDiscordActivityRoomAction
{
    /**
     * @param  array{instance_id: string, channel_id: string|null, guild_id: string|null, participants: list<array{id: string, username: string, global_name: string|null}>}  $snapshot
     */
    public function handle(array $snapshot): DiscordActivityRoom
    {
        try {
            return $this->sync($snapshot);
        } catch (UniqueConstraintViolationException) {
            return $this->sync($snapshot);
        }
    }

    /**
     * @param  array{instance_id: string, channel_id: string|null, guild_id: string|null, participants: list<array{id: string, username: string, global_name: string|null}>}  $snapshot
     */
    private function sync(array $snapshot): DiscordActivityRoom
    {
        return DB::transaction(function () use ($snapshot): DiscordActivityRoom {
            $room = DiscordActivityRoom::query()
                ->where('instance_id', $snapshot['instance_id'])
                ->lockForUpdate()
                ->first();

            if ($room === null) {
                $room = DiscordActivityRoom::query()->create([
                    'instance_id' => $snapshot['instance_id'],
                    'channel_id' => $snapshot['channel_id'],
                    'guild_id' => $snapshot['guild_id'],
                ]);
            } else {
                $room->update([
                    'channel_id' => $snapshot['channel_id'],
                    'guild_id' => $snapshot['guild_id'],
                ]);
            }

            $seenAt = now();
            $discordUserIds = [];

            foreach ($snapshot['participants'] as $participant) {
                $discordUserIds[] = $participant['id'];

                $room->members()->updateOrCreate(
                    ['discord_user_id' => $participant['id']],
                    [
                        'username' => $participant['username'],
                        'global_name' => $participant['global_name'],
                        'last_seen_at' => $seenAt,
                    ],
                );
            }

            $members = $room->members();

            if ($discordUserIds === []) {
                $members->delete();
            } else {
                $members->whereNotIn('discord_user_id', $discordUserIds)->delete();
            }

            return $room->load('members');
        });
    }
}
