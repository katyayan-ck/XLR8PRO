<?php

// app/Providers/SystemSettingServiceProvider.php

namespace App\Providers;

use App\Services\SystemSettingService;
use App\Facades\SystemSetting;
use Illuminate\Support\ServiceProvider;

class SystemSettingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register service
        $this->app->singleton(SystemSettingService::class, function ($app) {
            return new SystemSettingService();
        });

        // Register export/import service
        $this->app->singleton(
            \App\Services\SystemSettingExportImportService::class,
            function ($app) {
                return new \App\Services\SystemSettingExportImportService();
            }
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}


// Add to config/app.php in the 'providers' array:
// \App\Providers\SystemSettingServiceProvider::class,

// Add to config/app.php in the 'aliases' array:
// 'SystemSetting' => \App\Facades\SystemSetting::class,