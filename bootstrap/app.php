<?php

use App\Console\Commands\SendWhatsappFollowUps;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\NoIndexAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        SendWhatsappFollowUps::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(NoIndexAdmin::class);

        $middleware->alias([
            'admin.auth' => AuthenticateAdmin::class,
            'admin.permission' => CheckAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
