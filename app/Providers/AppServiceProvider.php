<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

use App\Services\RBACService;
// use App\Services\DataScopeService;
use App\Services\AuthService;
use App\Services\ApprovalService;
use App\Services\FirebaseService;
use App\Services\NotificationService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // // Register services as singletons for performance
        $this->app->singleton(RBACService::class, function ($app) {
            return new RBACService();
        });

        // $this->app->singleton(DataScopeService::class, function ($app) {
        //     return new DataScopeService();
        // });

        $this->app->bind(AuthService::class, function ($app) {
            return new AuthService(
                $app->make(\Illuminate\Http\Request::class),
                $app->make(\Illuminate\Cache\CacheManager::class),
                $app->make(\App\Services\OtpNotificationService::class)
            );
        });

        $this->app->singleton(ApprovalService::class, function ($app) {
            return new ApprovalService();
        });

        // Firebase Services
        $this->app->singleton(FirebaseService::class, function ($app) {
            return new FirebaseService();
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(FirebaseService::class)
            );
        });



        // $this->app->singleton(\App\Services\IAM\DataScopeService::class);
        $this->app->singleton(\App\Services\IAM\PostService::class);
        $this->app->singleton(\App\Services\IAM\ReportingService::class);
        $this->app->singleton(\App\Services\HR\HRJourneyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        foreach (glob(base_path('routes/backpack/*.php')) as $file) {
            require $file;
        }
    }
}
