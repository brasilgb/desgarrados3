<?php

use App\Console\Commands\SyncRegionalIntegrations;
use App\Console\Commands\SyncScheduledMediaVisibility;
use App\Http\Controllers\SitePageController;
use App\Http\Middleware\AddRobotsHeaders;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([SyncRegionalIntegrations::class, SyncScheduledMediaVisibility::class])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('campeira:sync ibge_locations')->monthlyOn(1, '02:00')->withoutOverlapping();
        $schedule->command('campeira:sync brasilapi_holidays')->yearlyOn(1, 1, '02:30')->withoutOverlapping();
        $schedule->command('campeira:sync mapa_zarc')->weeklyOn(1, '03:00')->withoutOverlapping();
        $schedule->command('media:sync-scheduled-visibility')->everyMinute()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            EnsureAccountIsActive::class,
            AddLinkHeadersForPreloadedAssets::class,
            AddRobotsHeaders::class,
        ]);

        $middleware->alias(['active' => EnsureAccountIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // abort(404) and missing models should show the styled portal/admin page, not the bare framework error.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return $request->is('admin') || $request->is('admin/*')
                ? app(SitePageController::class)->adminNotFound()
                : app(SitePageController::class)->notFound();
        });
    })->create();
