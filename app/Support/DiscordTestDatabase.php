<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class DiscordTestDatabase
{
    /**
     * Hosts that are allowed for the Discord branch database.
     *
     * @var list<string>
     */
    public const array LOCAL_HOSTS = [
        'pgsql',
        'localhost',
        '127.0.0.1',
    ];

    public static function assertLocal(): void
    {
        if (config('database.default') !== 'discord') {
            return;
        }

        $host = (string) config('database.connections.discord.host');

        if (! in_array($host, self::LOCAL_HOSTS, true)) {
            throw new RuntimeException('The Discord test database must use a local Postgres host.');
        }
    }
}
