<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Comment\CommentStatusEnum;
use App\Enums\Multiplayer\MultiplayerLobbyStatusEnum;
use App\Http\Resources\MultiplayerLobbyCardResource;
use App\Models\MultiplayerLobby;
use App\Models\Story;
use App\Support\StoryPlayability;
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
                'adaptation:id,story_id,adaptation_status',
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
        if (Auth::check()) {
            $existingGameId = Auth::user()->games()->where('story_id', $story->id)->value('id');
        }

        // Playable when published, on the review list, and boiler-complete.
        $isPlayable = StoryPlayability::isPlayable($story);

        return inertia('Stories/Show', [
            'story'          => $story->toResource(),
            'existingGameId' => $existingGameId,
            'isPlayable'     => $isPlayable,
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
