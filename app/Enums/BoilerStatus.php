<?php

declare(strict_types=1);

namespace App\Enums;

enum BoilerStatus: string
{
    case PENDING = 'pending';
    case DRAFTING = 'drafting';
    case DRAFTED = 'drafted';
    case FINALIZING = 'finalizing';
    case FINALIZED = 'finalized';
    case FAILED = 'failed';
}
