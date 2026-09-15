<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Game;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class GamePolicy
{
    public function view(User $user, Game $game): Response
    {
        return $game->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
