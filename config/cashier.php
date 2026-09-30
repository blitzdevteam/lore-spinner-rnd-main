<?php

declare(strict_types=1);

use Laravel\Cashier\Console\WebhookCommand;

return [
    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'events' => [
            ...WebhookCommand::DEFAULT_EVENTS,
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
        ],
    ],
];
