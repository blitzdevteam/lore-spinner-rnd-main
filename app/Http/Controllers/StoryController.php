<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Comment\CommentStatusEnum;
use App\Models\Chapter;
use App\Models\Story;
use App\Support\StoryLibraryVisibility;
use App\Support\StoryPlayability;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

final class StoryController extends Controller
{
    public function index(): Response
    {
        return inertia('Stories/Index', [
            'stories' => Story::query()
                ->with([
                    'category:id,title',
                    'creator:id,first_name,last_name,username,avatar',
                    'media',
                    'adaptation:id,story_id,adaptation_status',
                ])
                ->select([
                    'id', 'category_id', 'creator_id', 'title', 'slug', 'teaser', 'status', 'rating', 'updated_at',
                ])
                ->withCount([
                    'chapters',
                    'comments',
                ])
                ->published()
                ->latest()
                ->get()
                ->filter(static fn (Story $story): bool => ! StoryLibraryVisibility::isHidden($story->slug))
                ->values()
                ->toResourceCollection(),
        ]);
    }

    public function show(Story $story): Response
    {
        $story
            ->load([
                'category:id,title',
                'creator:id,first_name,last_name,username,avatar',
                'media',
                'adaptation:id,story_id,adaptation_status',
                'chapters' => function (HasMany $query): void {
                    $query
                        ->with(['media'])
                        ->orderBy('position')
                        ->select(['id', 'story_id', 'position', 'title', 'status', 'teaser'])
                        ->withCount(['events']);
                },
                'comments' => function (MorphMany $query): void {
                    $query
                        ->with('author')
                        ->where('status', CommentStatusEnum::APPROVED)
                        ->latest()
                        ->select(['id', 'author_type', 'author_id', 'commentable_type', 'commentable_id', 'content', 'created_at']);
                },
            ])
            ->loadCount([
                'chapters',
                'comments',
            ]);

        $existingGameId = null;
        $completedEpisodeNumbers = [];
        $showFirstStoryTutorial = false;
        if (Auth::check()) {
            $user = Auth::user();
            $existingGame = $user->games()
                ->where('story_id', $story->id)
                ->first(['id', 'current_session_number', 'current_session_complete']);

            $existingGameId = $existingGame?->id;
            $currentSessionNumber = $existingGame?->current_session_number;
            if ($currentSessionNumber !== null) {
                $completedEpisodeNumbers = $story->chapters
                    ->filter(function (Chapter $chapter) use ($currentSessionNumber, $existingGame): bool {
                        return $chapter->position < $currentSessionNumber
                            || ($chapter->position === $currentSessionNumber && $existingGame->current_session_complete);
                    })
                    ->map(fn (Chapter $chapter): int => (int) $chapter->position)
                    ->values()
                    ->all();
            }
            $showFirstStoryTutorial = ! $user->games()->where('is_preview', false)->exists();
        }

        // Playable when published, on the review list, and boiler-complete.
        $isPlayable = StoryPlayability::isPlayable($story);

        return inertia('Stories/Show', [
            'story' => $story->toResource(),
            'existingGameId' => $existingGameId,
            'completedEpisodeNumbers' => $completedEpisodeNumbers,
            'isPlayable' => $isPlayable,
            'showFirstStoryTutorial' => $showFirstStoryTutorial,
        ]);
    }

    public function multiplayer(Story $story, Request $request): Response
    {
        $inviteRoomCode = $request->query('room');

        $story
            ->load([
                'category:id,title',
                'creator:id,first_name,last_name,username,avatar',
                'media',
            ])
            ->loadCount('chapters');

        return inertia('Multiplayer/Multiplayer', [
            'story' => $story->toResource(),
            'inviteRoomCode' => is_string($inviteRoomCode)
                ? mb_strtoupper(mb_trim($inviteRoomCode))
                : null,
        ]);
    }
}
