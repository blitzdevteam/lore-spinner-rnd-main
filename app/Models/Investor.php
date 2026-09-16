<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvestorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $access_enabled
 * @property string|null $notes
 * @property Carbon|null $access_granted_at
 * @property Carbon|null $access_blocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class Investor extends Model
{
    /** @use HasFactory<InvestorFactory> */
    use HasFactory;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'access_enabled' => 'boolean',
            'access_granted_at' => 'datetime',
            'access_blocked_at' => 'datetime',
        ];
    }
}
