<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        session()->reflash();

        return to_route('index');
    }
}
