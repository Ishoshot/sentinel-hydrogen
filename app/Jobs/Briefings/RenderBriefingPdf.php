<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Actions\Briefings\RenderBriefingOutputs;
use App\Enums\Queue\Queue;
use App\Models\BriefingGeneration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RenderBriefingPdf implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public BriefingGeneration $generation,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(RenderBriefingOutputs $renderBriefingOutputs): void
    {
        $renderBriefingOutputs->handle($this->generation);
    }
}
