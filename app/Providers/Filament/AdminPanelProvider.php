<?php

namespace App\Providers\Filament;

use App\Http\Controllers\Api\Verification\DocumentFileController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The reviewer's panel, at /backoffice.
 *
 * This is where verification happens: an admin reads an applicant's profile,
 * opens each submitted document and approves or rejects it. There are no API
 * endpoints for any of that - the mobile clients never review anybody, so the
 * review surface lives here and nowhere else.
 *
 * Who gets in is decided by `User::canAccessPanel()`, which admits active
 * admins only. Nothing else in this provider grants access.
 *
 * Deliberately absent:
 * - `->registration()`, because an admin is seeded, never self-registered.
 * - `->passwordReset()`, because `password_reset_tokens` is keyed by msisdn
 *   for the OTP flow, so Laravel's email-based broker cannot drive it.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('backoffice')
            ->login()
            ->brandName('Jatriq Admin')
            ->colors([
                // Sampled from the logo, the same navy the mobile app uses.
                'primary' => Color::hex('#123A6D'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // The "Open file" link on a submitted document. It opens in a new
            // tab carrying only this panel's session cookie, which the
            // stateless `auth:sanctum` API route never reads, so the panel
            // serves the file itself. Same controller, same owner-or-admin check.
            ->authenticatedRoutes(function (): void {
                Route::get('/documents/{document}', [DocumentFileController::class, 'show'])
                    ->name('documents.show');
            });
    }
}
