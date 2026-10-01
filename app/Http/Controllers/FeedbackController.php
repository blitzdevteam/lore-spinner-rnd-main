<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
        ]);

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->store('feedback-screenshots', 'public');
        }

        Feedback::create([
            'user_id' => $request->user()?->id,
            'content' => $validated['content'],
            'screenshot_path' => $screenshotPath,
            'page_url' => $request->header('referer'),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('flash', [
            'success' => ['Thanks for your feedback!'],
        ]);
    }

    public function screenshot(string $path): StreamedResponse
    {
        abort_unless(
            Auth::guard('manager')->check() || Auth::check(),
            403,
        );

        abort_unless(
            str_starts_with($path, 'feedback-screenshots/') && ! str_contains($path, '..'),
            404,
        );

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
