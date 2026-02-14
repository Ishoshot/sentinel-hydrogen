<?php

declare(strict_types=1);

namespace App\Jobs\Usage;

use App\Actions\Subscriptions\AggregateWorkspaceUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AggregateUsage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AggregateWorkspaceUsage $aggregateWorkspaceUsage): void
    {
        $aggregateWorkspaceUsage->handle();
    }
}
