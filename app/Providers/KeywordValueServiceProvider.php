<?php

namespace App\Providers;

use App\Services\KeywordValueService;
use Illuminate\Support\ServiceProvider;

class KeywordValueServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Register singleton instance
        $this->app->singleton('keyword-value', function ($app) {
            return new KeywordValueService();
        });

        // Alias for easier access (optional)
        $this->app->alias('keyword-value', KeywordValueService::class);
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        // Service is ready to use
    }
}
