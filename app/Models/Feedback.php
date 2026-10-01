<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $content
 * @property string|null $screenshot_path
 * @property string|null $page_url
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
final class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function screenshotUrl(): ?string
    {
        if (blank($this->screenshot_path)) {
            return null;
        }

        return route('feedback.screenshot', ['path' => $this->screenshot_path]);
    }

    protected static function booted(): void
    {
        self::deleting(function (Feedback $feedback): void {
            if (filled($feedback->screenshot_path)) {
                Storage::disk('public')->delete($feedback->screenshot_path);
            }
        });
    }
}
