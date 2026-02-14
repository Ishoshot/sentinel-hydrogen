<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\DeliverGeneratedBriefing;
use App\Enums\Queue\Queue;
use App\Models\BriefingGeneration;
use App\Models\BriefingSubscription;
use App\Services\Slack\Contracts\SlackServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverBriefing implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BriefingGeneration $generation,
        public string $channel,
        public BriefingSubscription $subscription,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(
        DeliverGeneratedBriefing $deliverGeneratedBriefing,
        SlackServiceContract $slackService,
    ): void {
        $deliverGeneratedBriefing->handle(
            generation: $this->generation,
            channel: $this->channel,
            subscription: $this->subscription,
            slackService: $slackService,
        );
    }
}
