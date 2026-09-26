<?php

use App\Console\Commands\ImportRbacMaster;
use App\Console\Commands\ImportUsersCommand;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckSuperAdmin;
use App\Http\Middleware\ValidateDevice;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'checkPermission' => CheckPermission::class,
            'checkSuperAdmin' => CheckSuperAdmin::class,
            'validate_device' => ValidateDevice::class,
        ]);
    })
    ->withCommands([
        ImportRbacMaster::class,
        ImportUsersCommand::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
