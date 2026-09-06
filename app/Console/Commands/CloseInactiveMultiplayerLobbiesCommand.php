<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Multiplayer\CloseInactiveMultiplayerLobbiesAction;
use Illuminate\Console\Command;

final class CloseInactiveMultiplayerLobbiesCommand extends Command
{
    protected $signature = 'multiplayer:lobbies:close-inactive';

    protected $description = 'Close inactive and empty multiplayer lobbies without deleting them';

    public function handle(CloseInactiveMultiplayerLobbiesAction $closeInactiveLobbies): int
    {
        $closedLobbies = $closeInactiveLobbies->handle();

        if ($closedLobbies > 0) {
            $this->info("Closed {$closedLobbies} inactive multiplayer lobby/lobbies.");
        }

        return self::SUCCESS;
    }
}
