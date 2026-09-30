<?php

declare(strict_types=1);

namespace App\Actions\User;

final readonly class CancelSubscriptionResult
{
    private function __construct(
        public bool $successful,
        public string $message,
    ) {}

    public static function canceled(string $message): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
