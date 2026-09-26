<?php

use App\Console\Commands\ImportRbacMaster;
use App\Console\Commands\ImportUsersCommand;
use App\Http\Middleware\CheckSuperAdmin;
use App\Http\Middleware\ValidateDevice;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'checkSuperAdmin' => CheckSuperAdmin::class,
            'validate_device' => ValidateDevice::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withCommands([
        ImportRbacMaster::class,
        ImportUsersCommand::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
