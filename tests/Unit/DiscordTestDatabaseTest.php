<?php

declare(strict_types=1);

use App\Support\DiscordTestDatabase;
use RuntimeException;

it('accepts a local discord database host', function () {
    config([
        'database.default' => 'discord',
        'database.connections.discord.host' => 'pgsql',
        'database.connections.discord.database' => 'lore_spinner_discord',
    ]);

    DiscordTestDatabase::assertLocal();

    expect(config('database.connections.discord.database'))->toBe('lore_spinner_discord')
        ->and(config('database.connections.discord.url'))->toBeNull();
});

it('refuses a remote discord database host', function () {
    config([
        'database.default' => 'discord',
        'database.connections.discord.host' => 'db.example.com',
    ]);

    expect(fn () => DiscordTestDatabase::assertLocal())
        ->toThrow(RuntimeException::class);
});
