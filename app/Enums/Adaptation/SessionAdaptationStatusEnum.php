<?php

declare(strict_types=1);

namespace App\Enums\Adaptation;

use App\Traits\EnumToArray;
use Filament\Support\Contracts\HasLabel;

enum SessionAdaptationStatusEnum: string implements HasLabel
{
    use EnumToArray;

    case PENDING = 'pending';
    case ENTRY_POINT_DIAGNOSIS = 'entry-point-diagnosis';
    case SESSION_ARCHITECTURE = 'session-architecture';
    case CHOICE_DESIGN = 'choice-design';
    case CONSEQUENCE_MAPPING = 'consequence-mapping';
    case SESSION_CLOSE = 'session-close';
    case EDITORIAL_VERIFICATION = 'editorial-verification';
    case RUNTIME_ASSEMBLY = 'runtime-assembly';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ENTRY_POINT_DIAGNOSIS => 'Entry Point Diagnosis',
            self::SESSION_ARCHITECTURE => 'Session Architecture',
            self::CHOICE_DESIGN => 'Choice Design',
            self::CONSEQUENCE_MAPPING => 'Consequence Mapping',
            self::SESSION_CLOSE => 'Session Close',
            self::EDITORIAL_VERIFICATION => 'Editorial Verification',
            self::RUNTIME_ASSEMBLY => 'Runtime Assembly',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    public function getSeverity(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ENTRY_POINT_DIAGNOSIS,
            self::SESSION_ARCHITECTURE,
            self::CHOICE_DESIGN,
            self::CONSEQUENCE_MAPPING,
            self::SESSION_CLOSE,
            self::EDITORIAL_VERIFICATION,
            self::RUNTIME_ASSEMBLY => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
        };
    }
}
