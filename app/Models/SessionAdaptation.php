<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BoilerStatus;
use App\Enums\Adaptation\SessionAdaptationStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_adaptation_id
 * @property int $session_number
 * @property SessionAdaptationStatusEnum $session_status
 * @property BoilerStatus $boiler_status
 * @property string|null $boiler_error
 * @property array|null $entry_point_diagnosis
 * @property array|null $boiler_draft
 * @property array|null $session_architecture
 * @property array|null $session_choice_design
 * @property array|null $choice_consequence_map
 * @property array|null $session_close_design
 * @property array|null $boiler_final_cut
 * @property array|null $editorial_verification
 * @property string|null $runtime_narrator_prompt
 * @property Carbon|null $runtime_narrator_assembled_at
 * @property Carbon|null $boiler_generated_at
 * @property Carbon|null $boiler_finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StoryAdaptation $storyAdaptation
 * @property-read PlayableSessionCut|null $playableCut
 */
final class SessionAdaptation extends Model
{
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'session_status' => SessionAdaptationStatusEnum::class,
        'boiler_status' => BoilerStatus::class,
        'entry_point_diagnosis' => 'json',
        'boiler_draft' => 'json',
        'session_architecture' => 'json',
        'session_choice_design' => 'json',
        'choice_consequence_map' => 'json',
        'session_close_design' => 'json',
        'boiler_final_cut' => 'json',
        'editorial_verification' => 'json',
        'boiler_generated_at' => 'datetime',
        'boiler_finalized_at' => 'datetime',
        'runtime_narrator_assembled_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StoryAdaptation, $this>
     */
    public function storyAdaptation(): BelongsTo
    {
        return $this->belongsTo(StoryAdaptation::class);
    }

    public function playableCut(): HasOne
    {
        return $this->hasOne(PlayableSessionCut::class);
    }
}
