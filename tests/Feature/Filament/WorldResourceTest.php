<?php

declare(strict_types=1);

use App\Enums\Story\StoryStatusEnum;
use App\Filament\Manager\Resources\Worlds\Pages\CreateWorld;
use App\Filament\Manager\Resources\Worlds\Pages\EditWorld;
use App\Filament\Manager\Resources\Worlds\Pages\ListWorlds;
use App\Filament\Manager\Resources\Worlds\WorldResource;
use App\Filament\Support\SameOriginMediaPreview;
use App\Models\Manager;
use App\Models\Story;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not send filament uploads to s3 from the browser', function () {
    config(['filesystems.default' => 's3']);

    expect(config('livewire.temporary_file_upload.disk'))->toBe('livewire-tmp')
        ->and(FileUploadConfiguration::isUsingS3())->toBeFalse();
});

it('registers worlds as a manager content resource', function () {
    expect(WorldResource::getModel())->toBe(World::class)
        ->and(WorldResource::getNavigationLabel())->toBe('Worlds')
        ->and(WorldResource::getNavigationGroup())->toBe('Content')
        ->and(array_keys(WorldResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('lets a manager open the worlds list and create pages', function () {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager')
        ->get(WorldResource::getUrl('index'))
        ->assertSuccessful();

    $this->actingAs($manager, 'manager')
        ->get(WorldResource::getUrl('create'))
        ->assertSuccessful();
});

it('creates a world with attached stories from the admin form', function () {
    Storage::fake('public');

    $manager = Manager::factory()->create();
    $story = Story::factory()->create([
        'title' => 'Emma',
        'status' => StoryStatusEnum::PUBLISHED,
        'published_at' => now(),
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(CreateWorld::class)
        ->fillForm([
            'title' => 'Jane Austen',
            'slug' => 'jane-austen',
            'tagline' => 'Find love without losing yourself.',
            'tags' => ['Destiny', 'Courage', 'Control'],
            'position' => 1,
            'is_active' => true,
            'stories' => [$story->id],
            'background' => UploadedFile::fake()->image('austen.jpg'),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $world = World::query()->where('slug', 'jane-austen')->first();

    expect($world)->not->toBeNull()
        ->and($world?->title)->toBe('Jane Austen')
        ->and($world?->tagline)->toBe('Find love without losing yourself.')
        ->and($world?->tags)->toBe(['Destiny', 'Courage', 'Control'])
        ->and($world?->stories()->pluck('stories.id')->all())->toBe([$story->id])
        ->and($world?->getFirstMediaUrl('background'))->not->toBeEmpty();
});

it('lets a manager edit an existing world', function () {
    $manager = Manager::factory()->create();
    $world = World::factory()->create([
        'title' => 'Jane Austen',
        'slug' => 'jane-austen',
    ]);

    $this->actingAs($manager, 'manager')
        ->get(WorldResource::getUrl('edit', ['record' => $world]))
        ->assertSuccessful();

    expect(EditWorld::class)->toBeString()
        ->and(ListWorlds::class)->toBeString();
});

it('previews an existing world background from the app origin instead of object storage', function () {
    Storage::fake('public');

    $manager = Manager::factory()->create();
    $world = World::factory()->create([
        'title' => 'World of Oz',
        'slug' => 'world-of-oz',
    ]);
    $world->addMedia(UploadedFile::fake()->image('oz.png'))
        ->toMediaCollection('background');

    $media = $world->getFirstMedia('background');

    expect($media)->not->toBeNull();

    $previewUrl = SameOriginMediaPreview::url($media);

    expect($previewUrl)->toContain('/filament/media/'.$media->uuid)
        ->and($previewUrl)->not->toContain('cloudflarestorage');

    $this->actingAs($manager, 'manager')
        ->get($previewUrl)
        ->assertSuccessful();

    Livewire::actingAs($manager, 'manager')
        ->test(EditWorld::class, ['record' => $world->getKey()])
        ->assertSuccessful();
});
