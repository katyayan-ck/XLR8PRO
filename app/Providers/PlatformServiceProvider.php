<?php

namespace App\Providers;

use App\Services\Platform\Approval\ApprovalService;
use App\Services\Platform\Approval\RuleService;
use App\Services\Platform\Approval\TopicService;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Comms\CommsRouter;
use App\Services\Platform\Comms\EmailService;
use App\Services\Platform\Comms\SmsService;
use App\Services\Platform\Comms\TelephonyService;
use App\Services\Platform\Comms\WhatsAppService;
use App\Services\Platform\Docs\DocsService;
use App\Services\Platform\Notify\NotifyService;
use App\Services\Platform\Settings\SettingsService;
use App\Services\Platform\Task\TaskService;
use App\Services\Platform\Templates\TemplateService;
use App\Services\Platform\Ticket\TicketService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Platform utilities (FRS v1.1, DEC-061..065): services are singletons behind facades
 * (App\Support\Facades\*); Blade gets @setting / @feature and <x-notify|chat|docs|task|…> components.
 */
class PlatformServiceProvider extends ServiceProvider
{
    /** @var list<class-string> */
    private const SINGLETONS = [
        SettingsService::class,
        NotifyService::class,
        ChatService::class,
        DocsService::class,
        TaskService::class,
        TicketService::class,
        TopicService::class,
        RuleService::class,
        ApprovalService::class,
        TemplateService::class,
        CommsRouter::class,
        EmailService::class,
        SmsService::class,
        WhatsAppService::class,
        TelephonyService::class,
    ];

    public function register(): void
    {
        foreach (self::SINGLETONS as $class) {
            if (class_exists($class)) {
                $this->app->singleton($class);
            }
        }
    }

    public function boot(): void
    {
        Blade::directive('setting', fn (string $expression) => "<?php echo e(setting({$expression})); ?>");
        Blade::if('feature', fn (string $key) => feature($key));
    }
}
