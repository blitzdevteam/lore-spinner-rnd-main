<?php

declare(strict_types=1);

use App\Filament\Manager\Resources\Feedback\FeedbackResource;
use App\Models\Feedback;
use App\Models\Manager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('stores a page screenshot with the feedback message', function (): void {
    Storage::fake('public');

    $this->from('/pricing')
        ->post(route('feedback.store'), [
            'content' => 'The pricing cards overlap on mobile.',
            'screenshot' => UploadedFile::fake()->image('feedback-screenshot.jpg'),
        ])
        ->assertRedirect('/pricing');

    $feedback = Feedback::query()->first();

    expect($feedback)->not->toBeNull()
        ->and($feedback?->content)->toBe('The pricing cards overlap on mobile.')
        ->and($feedback?->screenshot_path)->toStartWith('feedback-screenshots/')
        ->and(Storage::disk('public')->exists((string) $feedback?->screenshot_path))->toBeTrue();
});

it('shows the stored screenshot in the manager feedback panel', function (): void {
    Storage::fake('public');

    $path = 'feedback-screenshots/shot.jpg';
    Storage::disk('public')->put($path, UploadedFile::fake()->image('shot.jpg')->getContent());

    $feedback = Feedback::query()->create([
        'content' => 'The send button does nothing.',
        'screenshot_path' => $path,
        'page_url' => 'http://localhost/pricing',
    ]);

    $manager = Manager::factory()->create();
    $screenshotUrl = route('feedback.screenshot', ['path' => $path]);

    $screenshot = $this->actingAs($manager, 'manager')->get($screenshotUrl);

    $screenshot->assertSuccessful();

    expect($screenshot->headers->get('content-type'))->toStartWith('image/');

    $this->actingAs($manager, 'manager')
        ->get(FeedbackResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($screenshotUrl, false);

    $this->actingAs($manager, 'manager')
        ->get(FeedbackResource::getUrl('view', ['record' => $feedback]))
        ->assertSuccessful()
        ->assertSee('Page screenshot', false)
        ->assertSee($screenshotUrl, false);
});

it('hides feedback screenshots from guests and from other public files', function (): void {
    Storage::fake('public');

    Storage::disk('public')->put('feedback-screenshots/shot.jpg', 'jpeg-bytes');
    Storage::disk('public')->put('stories/cover.jpg', 'secret-bytes');

    $this->get(route('feedback.screenshot', ['path' => 'feedback-screenshots/shot.jpg']))
        ->assertForbidden();

    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager')
        ->get(route('feedback.screenshot', ['path' => 'stories/cover.jpg']))
        ->assertNotFound();
});

it('captures a screenshot in the feedback widget before submit', function (): void {
    $widget = file_get_contents(resource_path('js/components/FeedbackWidget.vue'));

    expect($widget)
        ->toContain("from 'html2canvas-pro'")
        ->toContain('capturePageScreenshot')
        ->toContain('screenshot');
});
