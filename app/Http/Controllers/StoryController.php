<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Comment\CommentStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Enums\Story\StoryStatusEnum;
use App\Http\Controllers\User\GameController;
use App\Http\Resources\MultiplayerLobbyCardResource;
use App\Models\MultiplayerLobby;
use App\Models\Story;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

final class StoryController extends Controller
{
    public function index(): Response
    {
        $multiplayerRooms = MultiplayerLobby::query()
            ->where('status', MultiplayerLobbyStatusEnum::Waiting)
            ->with([
                'story' => function (BelongsTo $query): void {
                    $query->with('media');
                },
            ])
            ->withCount('members')
            ->latest()
            ->get()
            ->filter(static fn (MultiplayerLobby $lobby): bool => (int) $lobby->members_count < $lobby->max_players)
            ->values();

        return inertia('Stories/Index', [
            'stories' => Story::query()
                ->with([
                    'category:id,title',
                    'creator:id,first_name,last_name,username,avatar',
                    'media',
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
                ->toResourceCollection(),

            'multiplayerRooms' => MultiplayerLobbyCardResource::collection($multiplayerRooms),
        ]);
    }

    public function show(Story $story): Response
    {
        $story
            ->load([
                'category:id,title',
                'creator:id,first_name,last_name,username,avatar',
                'media',
                'chapters' => function (HasMany $query): void {
                    $query
                        ->with(['media'])
                        ->orderBy('position')
                        ->select(['id', 'story_id', 'title', 'status', 'teaser'])
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
        $showFirstStoryTutorial = false;
        if (Auth::check()) {
            $user = Auth::user();
            $existingGameId = $user->games()->where('story_id', $story->id)->value('id');
            $showFirstStoryTutorial = ! $user->games()->where('is_preview', false)->exists();
        }

        // Playable when published and on the launch whitelist (matches GameController::store).
        $isPlayable = $story->status === StoryStatusEnum::PUBLISHED
            && in_array($story->slug, GameController::LAUNCH_SLUGS, true);

        return inertia('Stories/Show', [
            'story'          => $story->toResource(),
            'existingGameId' => $existingGameId,
            'isPlayable'     => $isPlayable,
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
                ? mb_strtoupper(trim($inviteRoomCode))
                : null,
        ]);
    }
}
