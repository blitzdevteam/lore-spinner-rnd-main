<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AnthropicThinkingPolicy;
use App\Auth\EnforceLoginLockout;
use App\Filament\Support\EnsureLocalLivewireUploads;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;
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

        ResetPassword::createUrlUsing(function (CanResetPassword $notifiable, string $token): string {
            return route('user.authentication.reset-password.create', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        Http::globalRequestMiddleware(AnthropicThinkingPolicy::disableOpusThinking(...));

        $cashierPath = config('cashier.path', 'stripe');
        $cashierWebhookPath = is_string($cashierPath) && $cashierPath !== ''
            ? mb_trim($cashierPath, '/').'/*'
            : 'stripe/*';

        ValidateCsrfToken::except([$cashierWebhookPath]);

        Event::listen(Attempting::class, [EnforceLoginLockout::class, 'blockLockedAccount']);
        Event::listen(Failed::class, [EnforceLoginLockout::class, 'recordFailure']);
        Event::listen(Login::class, [EnforceLoginLockout::class, 'clearAfterSuccess']);

        Event::listen(WebhookHandled::class, static function (WebhookHandled $event): void {
            app(SubscriptionEntitlementService::class)->syncFromWebhook($event->payload);
        });

        Event::listen(WebhookReceived::class, static function (WebhookReceived $event): void {
            app(SubscriptionEntitlementService::class)->syncCheckoutSessionFromWebhook($event->payload);
        });

        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }

        $this->app->booted(static function (): void {
            EnsureLocalLivewireUploads::configure();
        });
    }
}
