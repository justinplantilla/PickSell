<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Pickrush (logistics host) first, so its "/" and "/login" win on that host.
        web: [__DIR__.'/../routes/pickrush.php', __DIR__.'/../routes/web.php'],
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Pickrush/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Pickrush\Http\Middleware\PickrushSession::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->web(append: [\App\Pickrush\Http\Middleware\IsolatePickrushHost::class]);
        // Isolation must answer 404 before auth redirects a marketplace page to a login screen.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Pickrush\Http\Middleware\IsolatePickrushHost::class,
        );

        $middleware->alias([
            'admin'  => \App\Http\Middleware\AdminMiddleware::class,
            'seller' => \App\Http\Middleware\SellerMiddleware::class,
            'buyer'  => \App\Http\Middleware\BuyerMiddleware::class,
            'pickrush.role' => \App\Pickrush\Http\Middleware\StaffRole::class,
            'pickrush.station' => \App\Pickrush\Http\Middleware\StationStaff::class,
            'pickrush.province' => \App\Pickrush\Http\Middleware\ProvinceStaff::class,
            'pickrush.hub' => \App\Pickrush\Http\Middleware\HubStaff::class,
            'pickrush.rider' => \App\Pickrush\Http\Middleware\RiderToken::class,
        ]);

        $onPickrush = fn (Request $request) => \App\Pickrush\Support\PickrushHost::matches($request);
        $middleware->redirectGuestsTo(fn (Request $request) => $onPickrush($request) ? route('pickrush.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $onPickrush($request) ? route('pickrush.dashboard') : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Audit refused admin requests, then fall through to the normal error response.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            app(\App\Services\AuditLogger::class)->recordDenial($request, $e);

            return null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
