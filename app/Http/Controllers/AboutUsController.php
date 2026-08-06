<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Response;

final class AboutUsController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('AboutUs');
    }
}
