<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\PurgeExpiredBriefings;
use App\Enums\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CleanupExpiredBriefings implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(PurgeExpiredBriefings $purgeExpiredBriefings): void
    {
        $purgeExpiredBriefings->handle();
    }
}
