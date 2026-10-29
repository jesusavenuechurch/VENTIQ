<?php

namespace App\Support;

/**
 * Background work run inside a web request, as it is with no queue worker
 * (QUEUE_CONNECTION=sync, a local copy): PHP would stop it after
 * max_execution_time (30 seconds) while it waits on the Mac mini.
 */
class LongRunning
{
    public static function allow(int $seconds = 600): void
    {
        if (config('queue.default') === 'sync' && function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
    }
}
