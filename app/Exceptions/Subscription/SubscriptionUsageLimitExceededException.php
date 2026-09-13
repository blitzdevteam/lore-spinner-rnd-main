<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use RuntimeException;

final class SubscriptionUsageLimitExceededException extends RuntimeException
{
    public const ACTIONS = 'actions';

    public const VOICE = 'voice';

    public function __construct(private readonly string $resource)
    {
        parent::__construct($resource === self::VOICE
            ? 'Your voice narration allowance has been reached for this billing period.'
            : 'Your action allowance has been reached for this billing period.');
    }

    public function resource(): string
    {
        return $this->resource;
    }
}
