<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Runtime\AchievementLibrary;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class AchievementController extends Controller
{
    public function __invoke(
        #[CurrentUser] User $user,
        AchievementLibrary $library,
    ): JsonResponse {
        return response()->json([
            'stories' => $library->forUser($user),
        ]);
    }
}
