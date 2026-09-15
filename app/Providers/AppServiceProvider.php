<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AnthropicThinkingPolicy;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Http::globalRequestMiddleware(AnthropicThinkingPolicy::disableOpusThinking(...));

        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }
    }
}
