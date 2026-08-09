<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Manager\Resources\Feedback\FeedbackResource;
use App\Filament\Manager\Resources\Feedback\Pages\ListFeedbacks;
use App\Filament\Manager\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Manager\Widgets;
use App\Filament\Manager\Widgets\Analytics\AnalyticsFunnelWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsKpiWidget;
use App\Filament\Manager\Widgets\Analytics\AnalyticsRetentionWidget;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

final class ManagerPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Register Livewire components directly — bypasses Filament's component
        // cache which would otherwise skip these pages if the cache predates them.
        Livewire::component(
            'app.filament.manager.resources.feedback.pages.list-feedbacks',
            ListFeedbacks::class,
        );
        Livewire::component(
            'app.filament.manager.resources.feedback.pages.view-feedback',
            ViewFeedback::class,
        );

        // Analytics widgets are NOT in discoverWidgets (they would appear on the
        // main dashboard and crash if analytics tables do not exist). Register them
        // as Livewire components manually so AnalyticsDashboard can render them.
        Livewire::component(
            'app.filament.manager.widgets.analytics.analytics-kpi-widget',
            AnalyticsKpiWidget::class,
        );
        Livewire::component(
            'app.filament.manager.widgets.analytics.analytics-funnel-widget',
            AnalyticsFunnelWidget::class,
        );
        Livewire::component(
            'app.filament.manager.widgets.analytics.analytics-retention-widget',
            AnalyticsRetentionWidget::class,
        );

        // Register the nav item on every manager request. FeedbackResource uses
        // $isDiscovered = false so it never enters getResources(), meaning
        // mountNavigation() would skip it. This serving() callback adds it back.
        //
        // NOTE: Do NOT guard on getCurrentPanel() here — DispatchServingFilamentEvent
        // fires before SetUpPanel middleware, so getCurrentPanel() is always null at
        // this point. getCurrentOrDefaultPanel() (used inside registerNavigationItems)
        // falls back to the default panel, which is the manager panel.
        Filament::serving(static function (): void {
            FeedbackResource::registerNavigationItems();
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->brandName('Lore Spinner')
            ->id('manager')
            ->path('manager')
            ->login()
            ->loginRouteSlug('authentication/login')
            ->colors([
                'primary' => Color::Purple,
            ])
            ->topbar(false)
            ->discoverResources(in: app_path('Filament/Manager/Resources'), for: 'App\Filament\Manager\Resources')
            // authenticatedRoutes() is NOT stored in the Filament component cache,
            // so this closure always runs regardless of cache state — this is the
            // reliable path to get /manager/feedback routes registered.
            ->authenticatedRoutes(function (Panel $panel): void {
                FeedbackResource::registerRoutes($panel);
            })
            ->discoverPages(in: app_path('Filament/Manager/Pages'), for: 'App\Filament\Manager\Pages')
            // Global widgets are listed explicitly in ->widgets() below.
            // Analytics widgets are NOT discovered here — they are registered as
            // Livewire components in boot() so they only render on the analytics page.
            ->pages([
                Dashboard::class,
            ])
            ->viteTheme('resources/css/filament/manager/theme.css')
            ->widgets([
                Widgets\PlatformStatsOverview::class,
                Widgets\SignupChart::class,
                Widgets\EngagementStats::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('manager');
    }
}
