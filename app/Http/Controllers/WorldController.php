<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ComingSoonStory\ComingSoonStorySectionEnum;
use App\Models\ComingSoonStory;
use App\Models\World;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Inertia\Response;

final class WorldController extends Controller
{
    public function index(): Response
    {
        return inertia('Worlds/Index', [
            'worlds' => World::query()
                ->active()
                ->with($this->worldRelations())
                ->withCount([
                    'stories' => fn ($query) => $query->published(),
                ])
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->toResourceCollection(),
        ]);
    }

    public function show(World $world): Response
    {
        abort_unless($world->is_active, 404);

        $world
            ->load($this->worldRelations())
            ->loadCount([
                'stories' => fn ($query) => $query->published(),
            ]);

        return inertia('Worlds/Show', [
            'world' => $world->toResource(),
            'otherWorlds' => World::query()
                ->active()
                ->whereKeyNot($world->id)
                ->with($this->worldRelations())
                ->withCount([
                    'stories' => fn ($query) => $query->published(),
                ])
                ->orderBy('position')
                ->orderBy('id')
                ->limit(3)
                ->get()
                ->toResourceCollection(),
            'worldsCount' => World::query()->active()->count(),
            'comingSoonStories' => ComingSoonStory::query()
                ->with(['media'])
                ->active()
                ->where('section', ComingSoonStorySectionEnum::COMING_SOON)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->toResourceCollection(),
        ]);
    }

    /**
     * @return array<string, callable>
     */
    private function worldRelations(): array
    {
        return [
            'media',
            'stories' => function (BelongsToMany $query): void {
                $query
                    ->published()
                    ->with(['media', 'category:id,title', 'adaptation:id,story_id,adaptation_status'])
                    ->orderByPivot('position');
            },
        ];
    }
}
