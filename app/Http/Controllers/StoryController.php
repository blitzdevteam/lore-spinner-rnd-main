<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Comment\CommentStatusEnum;
use App\Models\Story;
use App\Support\StoryEpisodeCatalog;
use App\Support\StoryLibraryVisibility;
use App\Support\StoryPlayability;
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
                'adaptation',
                'adaptation.sessionAdaptations:id,story_adaptation_id,session_number,session_status,boiler_status,entry_point_diagnosis',
                'comments' => function (MorphMany $query): void {
                    $query
                        ->with('author')
                        ->where('status', CommentStatusEnum::APPROVED)
                        ->latest()
                        ->select(['id', 'author_type', 'author_id', 'commentable_type', 'commentable_id', 'content', 'created_at']);
                },
            ])
            ->loadCount([
                'comments',
            ]);

        $existingGame = null;
        $showFirstStoryTutorial = false;
        if (Auth::check()) {
            $user = Auth::user();
            $existingGame = $user->games()
                ->where('story_id', $story->id)
                ->first([
                    'id',
                    'current_session_number',
                    'current_session_complete',
                    'current_story_cycle_number',
                ]);

            $showFirstStoryTutorial = ! $user->games()->where('is_preview', false)->exists();
        }

        // Episodes are adaptation sessions; chapters stay an authoring detail.
        $episodes = StoryEpisodeCatalog::for($story, $existingGame);

        // Playable when published, on the review list, and boiler-complete.
        $isPlayable = StoryPlayability::isPlayable($story);

        return inertia('Stories/Show', [
            'story' => $story->toResource(),
            'episodes' => $episodes,
            'existingGameId' => $existingGame?->id,
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
