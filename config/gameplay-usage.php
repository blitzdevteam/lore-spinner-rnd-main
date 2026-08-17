<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Launch gameplay allowance
|--------------------------------------------------------------------------
|
| These values are the temporary default entitlement while subscriptions are
| being introduced. GameplayAllowanceResolver is the only consumer, so a
| billing integration can replace this per user without changing the UI.
|
*/

return [
    'default_plan' => [
        'turns' => (int) env('GAMEPLAY_TURNS_INCLUDED', 250),
        'voice_minutes' => (int) env('GAMEPLAY_VOICE_MINUTES_INCLUDED', 60),
    ],
];
