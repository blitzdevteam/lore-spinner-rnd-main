<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;
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

        $cashierPath = config('cashier.path', 'stripe');
        $cashierWebhookPath = is_string($cashierPath) && $cashierPath !== ''
            ? trim($cashierPath, '/').'/*'
            : 'stripe/*';

        ValidateCsrfToken::except([$cashierWebhookPath]);

        Event::listen(WebhookHandled::class, static function (WebhookHandled $event): void {
            app(SubscriptionEntitlementService::class)->syncFromWebhook($event->payload);
        });

        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }
    }
}
