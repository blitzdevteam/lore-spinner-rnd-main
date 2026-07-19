<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $playable_session_cut_id
 * @property int $event_id
 * @property string $primary_role
 * @property string|null $included_moment_key
 * @property string $why
 * @property string|null $reintroduction_note
 * @property-read PlayableSessionCut $cut
 * @property-read Event $event
 */
final class EventPlayabilityRole extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    public function cut(): BelongsTo
    {
        return $this->belongsTo(PlayableSessionCut::class, 'playable_session_cut_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
