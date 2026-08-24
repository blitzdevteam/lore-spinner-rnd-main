<?php

declare(strict_types=1);

namespace App\Exceptions\Multiplayer;

use RuntimeException;

final class MultiplayerLobbyJoinException extends RuntimeException
{
    public static function isNotWaiting(): self
    {
        return new self('This lobby is no longer accepting players.');
    }

    public static function isFull(): self
    {
        return new self('This lobby is already full.');
    }
}
