<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'checkPermission' => \App\Http\Middleware\CheckPermission::class,
            'checkSuperAdmin' => \App\Http\Middleware\CheckSuperAdmin::class,
            'validate_device' => \App\Http\Middleware\ValidateDevice::class,
        ]);
    })
    ->withCommands([                                        // ← ADD THIS
        \App\Console\Commands\ImportRbacMasterCommand::class,
        \App\Console\Commands\ImportUsersCommand::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
