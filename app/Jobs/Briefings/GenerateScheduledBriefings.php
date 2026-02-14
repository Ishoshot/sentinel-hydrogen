<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\ProcessScheduledBriefings;
use App\Enums\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generate briefings for all due scheduled subscriptions.
 */
final class GenerateScheduledBriefings implements ShouldQueue
{
    use Queueable;

    /** Create a new job instance. */
    /**
     * Create a new GenerateScheduledBriefings instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /** Execute the job. */
    public function handle(ProcessScheduledBriefings $processScheduledBriefings): void
    {
        $processScheduledBriefings->handle();
    }
}
