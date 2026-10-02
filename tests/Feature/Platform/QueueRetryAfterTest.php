<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * BUG-230: a queue's `retry_after` must be longer than every job's `$timeout`, or a job still running is handed to a
 * second worker and runs twice (pricing imports run up to 1800 s). Holds for the database queue used today and the
 * Redis queue planned for UAT / production (O7).
 */
class QueueRetryAfterTest extends TestCase
{
    public function test_every_job_timeout_is_shorter_than_the_queue_retry_after(): void
    {
        $timeouts = [];
        foreach (File::allFiles(app_path('Jobs')) as $file) {
            if (preg_match('/public\s+(?:int\s+)?\$timeout\s*=\s*(\d+)/', $file->getContents(), $m)) {
                $timeouts[$file->getRelativePathname()] = (int) $m[1];
            }
        }
        $this->assertNotEmpty($timeouts);

        foreach (['database', 'redis'] as $connection) {
            $retryAfter = (int) config("queue.connections.{$connection}.retry_after");
            $tooLong = array_filter($timeouts, fn (int $t) => $t >= $retryAfter);
            $this->assertSame([], $tooLong, "{$connection} retry_after {$retryAfter} s is not longer than these job timeouts");
        }
    }
}
