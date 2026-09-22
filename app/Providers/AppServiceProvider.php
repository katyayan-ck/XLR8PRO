<?php

namespace App\Providers;

use App\Services\ApprovalService;
use App\Services\AuthService;
use App\Services\EnquiryReferenceService;
// use App\Services\DataScopeService;
use App\Services\FirebaseService;
use App\Services\HR\EmployeeJourneyService;
use App\Services\HR\HRJourneyService;
use App\Services\IAM\PostService;
use App\Services\IAM\ReportingService;
use App\Services\IdentifierService;
use App\Services\NotificationService;
use App\Services\OtpNotificationService;
use App\Services\RBACService;
use App\Services\Sales\Booking\BookingDeliveryService;
use App\Services\Sales\Booking\BookingDmsService;
use App\Services\Sales\Booking\BookingExchangeService;
use App\Services\Sales\Booking\BookingFinanceService;
use App\Services\Sales\Booking\BookingInsuranceService;
use App\Services\Sales\Booking\BookingKycService;
use App\Services\Sales\Booking\BookingRefundService;
use App\Services\Sales\Booking\BookingRtoService;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // // Register services as singletons for performance
        $this->app->singleton(RBACService::class, function ($app) {
            return new RBACService;
        });

        // $this->app->singleton(DataScopeService::class, function ($app) {
        //     return new DataScopeService();
        // });

        $this->app->bind(AuthService::class, function ($app) {
            return new AuthService(
                $app->make(Request::class),
                $app->make(CacheManager::class),
                $app->make(OtpNotificationService::class)
            );
        });

        $this->app->singleton(ApprovalService::class, function ($app) {
            return new ApprovalService;
        });

        // Firebase Services
        $this->app->singleton(FirebaseService::class, function ($app) {
            return new FirebaseService;
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(FirebaseService::class)
            );
        });

        // $this->app->singleton(\App\Services\IAM\DataScopeService::class);
        $this->app->singleton(PostService::class);
        $this->app->singleton(ReportingService::class);
        $this->app->singleton(HRJourneyService::class);
        $this->app->singleton(EmployeeJourneyService::class);
        $this->app->singleton(IdentifierService::class);
        $this->app->singleton(EnquiryReferenceService::class);

        $this->app->singleton(BookingKycService::class, function ($app) {
            return new BookingKycService($app->make(IdentifierService::class));
        });
        $this->app->singleton(BookingDmsService::class);
        $this->app->singleton(BookingInsuranceService::class);
        $this->app->singleton(BookingRtoService::class);
        $this->app->singleton(BookingDeliveryService::class);
        $this->app->singleton(BookingFinanceService::class);
        $this->app->singleton(BookingExchangeService::class);
        $this->app->singleton(BookingRefundService::class);

        // SuperAdmin wildcard bypass + user-level permission denial check — registered here in
        // register() (not boot()), and via afterResolving rather than the Gate facade, so this
        // callback attaches to the Gate BEFORE Spatie's own. Spatie's PermissionServiceProvider
        // registers its own Gate::before() inside ITS boot() (PermissionRegistrar::
        // registerPermissions()), which returns `true` immediately whenever the user has the
        // permission via role/direct grant — and Laravel's Gate stops at the FIRST non-null
        // "before" result. Registered in boot() (after all providers' register() phases), ours
        // would run SECOND and never get a chance to deny a permission the role already grants.
        // Since register() runs for every provider before boot() runs for any, resolving Gate's
        // afterResolving hook here guarantees ours attaches first — verified live: without this,
        // an explicit UserPermissionDenial had zero effect on a permission the user's role grants.
        $this->app->afterResolving(GateContract::class, function (GateContract $gate) {
            $gate->before(function ($user, string $ability) {
                if (! method_exists($user, 'isSuperAdmin')) {
                    return null;
                }

                if ($user->isSuperAdmin()) {
                    return true;
                }

                if (method_exists($user, 'deniesPermission') && $user->deniesPermission($ability)) {
                    return false;
                }

                return null;
            });
        });
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
