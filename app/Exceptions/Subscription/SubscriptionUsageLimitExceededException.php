<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use RuntimeException;

final class SubscriptionUsageLimitExceededException extends RuntimeException
{
    public const ACTIONS = 'actions';

    public const VOICE = 'voice';

    public const VOICE_UPGRADE_MESSAGE = 'This narration is longer than your remaining voice time. Update your subscription plan to keep listening.';

    public function __construct(private readonly string $resource)
    {
        parent::__construct($resource === self::VOICE
            ? self::VOICE_UPGRADE_MESSAGE
            : 'Your action allowance has been reached for this billing period.');
    }

    public function resource(): string
    {
        return $this->resource;
    }
}
