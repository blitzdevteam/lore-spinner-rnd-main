<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Game;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\GameRecapService;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use App\Models\User;

final class RecapController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        Game $game,
        GameRecapService $recapService,
    ): JsonResponse
    {
        abort_unless((int) $game->user_id === (int) $user->id, 403);

        return response()->json($recapService->generate($game));
    }
}
