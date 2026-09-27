<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ComingSoonStory;
use App\Models\Creator;
use App\Models\Game;
use App\Models\Story;
use App\Models\World;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(): Response
    {
        return inertia('Index', self::homepageProps());
    }

    /**
     * @return array<string, mixed>
     */
    public static function homepageProps(): array
    {
        return [
            'featuredStory' => fn () => Story::query()
                ->with([
                    'category:id,title',
                    'creator:id,first_name,last_name',
                    'media',
                ])
                ->where('slug', 'alices-adventures-in-wonderland')
                ->published()
                ->first()
                ?->toResource(),
            'lastGame' => fn () => Auth::check()
                ? Game::query()
                    ->where('user_id', Auth::id())
                    ->with([
                        'story' => fn ($q) => $q->with([
                            'media',
                            'category:id,title',
                            'creator:id,first_name,last_name,username',
                            'chapters' => fn ($chapters) => $chapters
                                ->select(['id', 'story_id', 'title', 'position', 'status'])
                                ->orderBy('position'),
                        ]),
                    ])
                    ->withCount('prompts')
                    ->latest('updated_at')
                    ->first()
                    ?->toResource()
                : null,
            'creators' => fn () => Creator::query()
                ->select([
                    'id', 'username', 'first_name', 'last_name', 'avatar', 'bio',
                ])
                ->with(['media'])
                ->withCount([
                    'stories',
                ])
                ->where('email', 'like', '%@lorespinner.com')
                ->orderByDesc('stories_count')
                ->get()
                ->toResourceCollection(),
            'stories' => fn () => Story::query()
                ->with([
                    'category:id,title',
                    'creator:id,first_name,last_name',
                    'media',
                    'adaptation:id,story_id,adaptation_status',
                ])
                ->select([
                    'id', 'category_id', 'creator_id', 'title', 'slug', 'teaser', 'status', 'rating', 'published_at', 'updated_at',
                ])
                ->withCount([
                    'chapters',
                    'comments',
                ])
                ->published()
                ->latest('published_at')
                ->get()
                ->toResourceCollection(),
            'comingSoonStories' => fn () => ComingSoonStory::query()
                ->with(['media'])
                ->active()
                ->orderBy('section')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->toResourceCollection(),
            'worlds' => function () {
                $featuredSlugs = array_values(config('stories.homepage_world_slugs', []));

                $base = fn () => World::query()
                    ->active()
                    ->with([
                        'media',
                        'stories' => fn ($query) => $query
                            ->published()
                            ->with(['media', 'category:id,title', 'adaptation:id,story_id,adaptation_status'])
                            ->orderByPivot('position'),
                    ])
                    ->withCount([
                        'stories' => fn ($query) => $query->published(),
                    ]);

                $featured = $base()
                    ->whereIn('slug', $featuredSlugs)
                    ->get()
                    ->sortBy(function (World $world) use ($featuredSlugs): int {
                        $index = array_search($world->slug, $featuredSlugs, true);

                        return $index === false ? PHP_INT_MAX : $index;
                    })
                    ->values();

                if ($featuredSlugs !== [] && $featured->count() === count($featuredSlugs)) {
                    return $featured->take(3)->toResourceCollection();
                }

                return $base()
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(3)
                    ->get()
                    ->toResourceCollection();
            },
            'worldsCount' => fn () => World::query()->active()->count(),
        ];
    }
}
