<?php

declare(strict_types=1);

use App\Http\Controllers;
use App\Http\Middleware\RecordPageView;
use Illuminate\Support\Facades\Route;

require __DIR__.'/routes/user.php';
require __DIR__.'/routes/writer.php';

// Manager — login as writer (accessible only while manager is authenticated)
Route::middleware(['auth:manager'])
    ->get('manager/writers/{writer}/login-as', Controllers\Manager\LoginAsWriterController::class)
    ->name('manager.login-as-writer');

// ─── Public marketing surfaces — page views tracked for Visits metric ────────
Route::middleware(RecordPageView::class)->group(function (): void {
    Route::get('/', Controllers\IndexController::class)->name('index');
    Route::get('about-us', Controllers\AboutUsController::class)->name('about-us');

    Route::resource('creators', Controllers\CreatorController::class)
        ->scoped(['creator' => 'username'])
        ->only(['index', 'show']);

    Route::resource('stories', Controllers\StoryController::class)
        ->scoped(['story' => 'slug'])
        ->only(['index', 'show']);
});

Route::get('old-homepage', Controllers\OldHomepageController::class)->name('old-homepage');

Route::get('design-rnd', fn () => inertia('DesignRnd'))->name('design-rnd');
Route::get('bg-rnd', fn () => inertia('BgRnd'))->name('bg-rnd');

Route::post('feedback', [Controllers\FeedbackController::class, 'store'])->name('feedback.store');
Route::get('feedback/screenshot/{path}', [Controllers\FeedbackController::class, 'screenshot'])
    ->where('path', '.+')
    ->name('feedback.screenshot');

// ─── Chaos Mode — experimental open runtime for Alice in Wonderland ───────────
// Isolated from the production game runtime. Stateless server; all session state
// is owned by the client. No auth required — this is a public experiment.
// To revert: remove this block and delete app/Http/Controllers/ChaosMode/,
// app/Ai/Agents/Chaos/, resources/views/ai/agents/chaos/, and resources/js/pages/ChaosMode.vue
Route::prefix('chaos-mode')->name('chaos-mode.')->group(function () {
    Route::get('/', [Controllers\ChaosMode\ChaosModeController::class, 'show'])->name('show');
    Route::post('/start', [Controllers\ChaosMode\ChaosModeController::class, 'start'])->name('start');
    Route::post('/turn', [Controllers\ChaosMode\ChaosModeController::class, 'turn'])->name('turn');
    Route::post('/continue', [Controllers\ChaosMode\ChaosModeController::class, 'continueSession'])->name('continue');
    Route::get('/{chaosSession}/tts/{turnIndex}', Controllers\ChaosMode\ChaosTtsController::class)->name('tts');
});

Route::get('expansion-status', function () {
    $symlink = public_path('storage');
    $symlinkExists = file_exists($symlink);
    $symlinkTarget = $symlinkExists ? (is_link($symlink) ? readlink($symlink) : 'NOT A SYMLINK') : 'MISSING';
    $storageDir = storage_path('app/public');
    $storageDirExists = is_dir($storageDir);
    $storageFiles = $storageDirExists ? count(glob($storageDir.'/*')) : 0;

    $stories = App\Models\Story::select('id', 'title', 'status', 'creator_id')
        ->with(['creator:id,first_name,last_name', 'media'])
        ->orderBy('id')
        ->get()
        ->map(function ($s) {
            $cover = $s->getFirstMedia('cover');
            $coverUrl = $s->getFirstMediaUrl('cover');
            $coverPath = $cover?->getPath();
            $fileOnDisk = $coverPath ? file_exists($coverPath) : false;

            return [
                'title' => $s->title,
                'status' => $s->status->value,
                'creator' => $s->creator?->first_name.' '.$s->creator?->last_name,
                'has_cover_record' => $cover !== null,
                'cover_url' => $coverUrl ?: null,
                'cover_path' => $coverPath,
                'file_on_disk' => $fileOnDisk,
            ];
        });

    $creators = App\Models\Creator::select('id', 'first_name', 'last_name', 'email')
        ->with('media')
        ->get()
        ->map(function ($c) {
            $avatar = $c->getFirstMedia('avatar');
            $avatarPath = $avatar?->getPath();

            return [
                'name' => $c->first_name.' '.$c->last_name,
                'email' => $c->email,
                'has_avatar_record' => $avatar !== null,
                'avatar_url' => $c->avatar ?: null,
                'file_on_disk' => $avatarPath ? file_exists($avatarPath) : false,
            ];
        });

    $chapters = App\Models\Chapter::select('id', 'story_id', 'position', 'title', 'teaser')
        ->with(['story:id,title', 'media'])
        ->whereHas('story', fn ($q) => $q->whereIn('title', [
            "Hemingway's War", 'High Stakes', 'Pieces of Eight', 'Time Machine',
            'B.U.G.S.', 'Dream Police', 'Necropolis', "PJ's", 'Wasteland',
        ]))
        ->orderBy('story_id')
        ->orderBy('position')
        ->get()
        ->map(function ($ch) {
            $cover = $ch->getFirstMedia('cover');

            return [
                'id' => $ch->id,
                'story' => $ch->story?->title,
                'position' => $ch->position,
                'title' => $ch->title,
                'teaser' => $ch->teaser,
                'has_cover' => $cover !== null,
            ];
        });

    return response()->json([
        'symlink' => ['exists' => $symlinkExists, 'target' => $symlinkTarget],
        'storage_dir' => ['exists' => $storageDirExists, 'file_count' => $storageFiles],
        'creators' => $creators,
        'stories' => $stories,
        'chapters' => $chapters,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
});
