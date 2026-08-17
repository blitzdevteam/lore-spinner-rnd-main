<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Authentication;

use App\Actions\User\UpdateUserAction;
use App\Http\Requests\User\Authentication\UpdateCompleteProfileRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

final class CompleteProfileController
{
    /**
     * Show the complete profile form.
     */
    public function edit(): Response
    {
        return inertia('User/Authentication/CompleteProfile');
    }

    /**
     * Update the user's profile to mark it as completed.
     */
    public function update(
        #[CurrentUser] User $user,
        UpdateCompleteProfileRequest $request,
        UpdateUserAction $updateUserAction,
    ): RedirectResponse {
        $updateUserAction->handle($user, $request->validated());

        return redirect()
            ->intended(route('user.dashboard.index'))
            ->with('success', 'Your profile has been updated');
    }
}
