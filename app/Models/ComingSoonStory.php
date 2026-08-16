<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use Database\Factories\ComingSoonStoryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property ComingSoonStorySectionEnum $section
 * @property string|null $teaser
 * @property string|null $category
 * @property string|null $rating
 * @property string|null $author
 * @property string|null $series_label
 * @property string|null $image_position
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ComingSoonStory extends Model implements HasMedia
{
    /** @use HasFactory<ComingSoonStoryFactory> */
    use HasFactory;

    use InteractsWithMedia;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('cover')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('public');
    }

    /**
     * @param  Builder<ComingSoonStory>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'section' => ComingSoonStorySectionEnum::class,
            'is_active' => 'boolean',
        ];
    }
}
