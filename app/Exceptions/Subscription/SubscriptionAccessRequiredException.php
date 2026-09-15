<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use RuntimeException;

final class SubscriptionAccessRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An active subscription is required to play.');
    }
}
