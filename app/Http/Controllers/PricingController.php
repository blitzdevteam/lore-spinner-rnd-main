<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;

final class PricingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $status = $request->string('checkout')->toString();

        return inertia('Pricing', [
            'checkoutStatus' => in_array($status, ['success', 'cancel'], true) ? $status : null,
        ]);
    }
}
