<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Models\Story;
use App\Models\World;
use App\Support\PublicMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('private');
    $this->withoutVite();
});

it('lets guests stream uploaded story covers from the app origin', function (): void {
    $story = Story::factory()->create([
        'title' => 'Emma',
        'slug' => 'emma',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $story->addMedia(UploadedFile::fake()->image('emma.jpg'))
        ->toMediaCollection('cover');

    $media = $story->getFirstMedia('cover');

    expect($media)->not->toBeNull()
        ->and($story->getFirstMediaUrl('cover'))->toBe(PublicMedia::url($media));

    $this->get(PublicMedia::url($media))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/jpeg');
});

it('lets guests stream uploaded world backgrounds from the app origin', function (): void {
    $world = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
    ]);
    $world->addMedia(UploadedFile::fake()->image('austen.png'))
        ->toMediaCollection('background');

    $media = $world->getFirstMedia('background');

    expect($media)->not->toBeNull()
        ->and($world->getFirstMediaUrl('background'))->toBe(PublicMedia::url($media));

    $this->get(PublicMedia::url($media))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png');
});

it('hides private story scripts from the public media route', function (): void {
    $story = Story::factory()->create();
    $story->addMediaFromString('The story begins on Baker Street.')
        ->usingFileName('script.txt')
        ->toMediaCollection('script');

    $media = $story->getFirstMedia('script');

    expect($media)->not->toBeNull();

    $this->get(route('media.show', $media))->assertNotFound();
});

it('sends same-origin cover urls on the library page', function (): void {
    $story = Story::factory()->create([
        'title' => 'Emma',
        'slug' => 'emma',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $story->addMedia(UploadedFile::fake()->image('emma.jpg'))
        ->toMediaCollection('cover');

    $coverUrl = $story->fresh()->getFirstMediaUrl('cover');

    expect($coverUrl)->toContain('/media/'.$story->getFirstMedia('cover')->uuid)
        ->and($coverUrl)->not->toContain('cloudflarestorage');

    $this->get(route('stories.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Stories/Index')
                ->where('stories.0.cover', $coverUrl)
                ->etc()
        );
});

it('does not crash pages when uploaded media has a null uuid', function (): void {
    $story = Story::factory()->create([
        'title' => 'Emma',
        'slug' => 'emma',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);
    $story->addMedia(UploadedFile::fake()->image('emma.jpg'))
        ->toMediaCollection('cover');

    $media = $story->getFirstMedia('cover');
    expect($media)->not->toBeNull();
    $media->forceFill(['uuid' => null])->saveQuietly();

    $this->get(route('stories.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Stories/Index')
                ->where('stories.0.cover', route('media.show', $media->fresh()->uuid))
                ->etc()
        );

    expect($media->fresh()?->uuid)->not->toBeEmpty();
});

it('sends same-origin background urls on the worlds page', function (): void {
    $world = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
        'is_active' => true,
        'position' => 1,
    ]);
    $world->addMedia(UploadedFile::fake()->image('austen.png'))
        ->toMediaCollection('background');

    $backgroundUrl = $world->fresh()->getFirstMediaUrl('background');

    expect($backgroundUrl)->toContain('/media/'.$world->getFirstMedia('background')->uuid);

    $this->get(route('worlds.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Worlds/Index')
                ->where('worlds.0.background', $backgroundUrl)
                ->etc()
        );
});
