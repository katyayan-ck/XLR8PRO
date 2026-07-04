<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Utilities\CommHistory\CommThread;

class SendHistoryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public CommThread $thread) {}

    public function handle()
    {
        // Add your NotificationService logic here (Firebase + in-app)
        // Example: app(NotificationService::class)->sendHistoryAlert($this->thread);
    }
}