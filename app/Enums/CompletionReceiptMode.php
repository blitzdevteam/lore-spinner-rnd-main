<?php

declare(strict_types=1);

namespace App\Enums;

enum CompletionReceiptMode: string
{
    case Session = 'session';
    case Story = 'story';
}
