<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

it('answers a discord ping', function () {
    $response = discordInteraction([
        'type' => 1,
    ]);

    $response->assertSuccessful()
        ->assertExactJson(['type' => 1]);
});

it('rejects a bad discord signature', function () {
    $body = json_encode(['type' => 1], JSON_THROW_ON_ERROR);

    $response = test()->call('POST', '/discord/interactions', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => str_repeat('ab', 64),
        'HTTP_X_SIGNATURE_TIMESTAMP' => (string) time(),
    ], $body);

    $response->assertUnauthorized();
});

it('starts the solo test from the play command', function () {
    $response = discordInteraction([
        'type' => 2,
        'application_id' => '100',
        'data' => ['name' => 'play'],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('type', 4)
        ->assertJsonPath('data.components.0.components.0.custom_id', 'solo:gate:open')
        ->assertJsonPath('data.components.0.components.1.label', 'Take the lantern');
});

it('advances the solo test when a choice is clicked', function () {
    $response = discordInteraction([
        'type' => 3,
        'application_id' => '100',
        'data' => ['custom_id' => 'solo:gate:open'],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('type', 7)
        ->assertJsonPath('data.components.0.components.0.custom_id', 'solo:hall:ring');
});

it('rejects a choice from a different application', function () {
    $response = discordInteraction([
        'type' => 3,
        'application_id' => '999',
        'data' => ['custom_id' => 'solo:gate:open'],
    ]);

    $response->assertUnauthorized();
});

it('registers the play command for a test guild', function () {
    config([
        'discord.application_id' => '100',
        'discord.bot_token' => 'test-token',
        'discord.guild_id' => '456',
    ]);

    Http::fake([
        'discord.com/*' => Http::response(['id' => '1', 'name' => 'play'], 200),
    ]);

    $this->artisan('discord:register-test-command')->assertSuccessful();

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://discord.com/api/v10/applications/100/guilds/456/commands'
            && $request['name'] === 'play'
            && $request->hasHeader('Authorization', 'Bot test-token');
    });
});

/**
 * @param  array<string, mixed>  $payload
 */
function discordInteraction(array $payload): TestResponse
{
    $keyPair = sodium_crypto_sign_keypair();
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_bin2hex(sodium_crypto_sign_publickey($keyPair));

    config([
        'discord.public_key' => $publicKey,
        'discord.application_id' => '100',
    ]);

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = (string) time();
    $signature = sodium_bin2hex(sodium_crypto_sign_detached($timestamp.$body, $secretKey));

    return test()->call('POST', '/discord/interactions', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => $signature,
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $body);
}
