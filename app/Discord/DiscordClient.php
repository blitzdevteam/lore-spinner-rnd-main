<?php

declare(strict_types=1);

namespace App\Discord;

use Illuminate\Support\Facades\Http;

final class DiscordClient
{
    private const string API_URL = 'https://discord.com/api/v10';

    public function clientId(): string
    {
        return (string) (config('discord.client_id') ?: config('discord.application_id'));
    }

    /**
     * Trade the code from the Embedded App SDK authorize command for a user access token.
     */
    public function exchangeCode(string $code): ?string
    {
        $clientId = $this->clientId();
        $clientSecret = (string) config('discord.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post(self::API_URL.'/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

        $accessToken = $response->json('access_token');

        return $response->successful() && is_string($accessToken) && $accessToken !== ''
            ? $accessToken
            : null;
    }

    /**
     * @return array{id: string, username: string, global_name: string|null}|null
     */
    public function currentUser(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get(self::API_URL.'/users/@me');

        $id = $response->json('id');
        $username = $response->json('username');
        $globalName = $response->json('global_name');

        if ($response->failed() || ! is_string($id) || ! is_string($username)) {
            return null;
        }

        return [
            'id' => $id,
            'username' => $username,
            'global_name' => is_string($globalName) && $globalName !== '' ? $globalName : null,
        ];
    }

    /**
     * Ask Discord whether the user is really connected to this activity instance.
     */
    public function instanceHasUser(string $instanceId, string $discordUserId): bool
    {
        $applicationId = (string) config('discord.application_id');
        $botToken = (string) config('discord.bot_token');

        if ($applicationId === '' || $botToken === '') {
            return false;
        }

        $response = Http::withHeaders(['Authorization' => 'Bot '.$botToken])
            ->acceptJson()
            ->get(self::API_URL."/applications/{$applicationId}/activity-instances/{$instanceId}");

        $users = $response->json('users');

        return $response->successful()
            && is_array($users)
            && in_array($discordUserId, $users, true);
    }
}
