<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One story repair review of a session, and what became of it.
 *
 * @property int $id
 * @property int $session_adaptation_id
 * @property string $source
 * @property string $status
 * @property array<int, array<string, string>> $findings
 * @property array<int, array<string, string>> $patches
 * @property array<int, array<string, string>> $rebinds
 * @property array<int, array<string, mixed>> $rejected
 * @property array<string, mixed>|null $backup
 * @property Carbon|null $applied_at
 * @property Carbon|null $reverted_at
 * @property-read SessionAdaptation $sessionAdaptation
 */
final class StoryRepairReport extends Model
{
    public const string STATUS_REVIEWED = 'reviewed';

    public const string STATUS_APPLIED = 'applied';

    public const string STATUS_REVERTED = 'reverted';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @return BelongsTo<SessionAdaptation, $this>
     */
    public function sessionAdaptation(): BelongsTo
    {
        return $this->belongsTo(SessionAdaptation::class);
    }

    protected function casts(): array
    {
        return [
            'findings' => 'json',
            'patches' => 'json',
            'rebinds' => 'json',
            'rejected' => 'json',
            'backup' => 'json',
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }
}
