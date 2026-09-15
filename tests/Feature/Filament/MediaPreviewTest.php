<?php

declare(strict_types=1);

use App\Filament\Support\EnsureLocalLivewireUploads;
use App\Filament\Support\SameOriginMediaPreview;
use App\Models\Manager;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

uses(RefreshDatabase::class);

it('moves livewire uploads off an s3 private disk so the browser never fetches r2', function () {
    $originalPrivateDisk = config('filesystems.disks.private');
    $originalLivewireDisk = config('livewire.temporary_file_upload.disk');

    config([
        'livewire.temporary_file_upload.disk' => 'private',
        'filesystems.disks.private.driver' => 's3',
        'filesystems.disks.private.key' => 'key',
        'filesystems.disks.private.secret' => 'secret',
        'filesystems.disks.private.region' => 'auto',
        'filesystems.disks.private.bucket' => 'bucket',
    ]);

    EnsureLocalLivewireUploads::configure();

    expect(config('livewire.temporary_file_upload.disk'))->toBe('livewire-tmp')
        ->and(config('filesystems.disks.livewire-tmp.driver'))->toBe('local')
        ->and(FileUploadConfiguration::isUsingS3())->toBeFalse();

    config([
        'filesystems.disks.private' => $originalPrivateDisk,
        'livewire.temporary_file_upload.disk' => $originalLivewireDisk,
    ]);
});

it('rejects guests from filament media previews', function () {
    Storage::fake('public');

    $world = World::factory()->create();
    $world->addMedia(UploadedFile::fake()->image('oz.png'))
        ->toMediaCollection('background');

    $this->get(SameOriginMediaPreview::url($world->getFirstMedia('background')))
        ->assertRedirect();
});

it('lets a manager stream a world background through the app origin', function () {
    Storage::fake('public');

    $manager = Manager::factory()->create();
    $world = World::factory()->create();
    $world->addMedia(UploadedFile::fake()->image('oz.png'))
        ->toMediaCollection('background');

    $media = $world->getFirstMedia('background');

    expect($media)->not->toBeNull();

    $response = $this->actingAs($manager, 'manager')
        ->get(SameOriginMediaPreview::url($media));

    $response->assertSuccessful();

    expect($response->headers->get('content-type'))->toStartWith('image/');
});
