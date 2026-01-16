<?php

declare(strict_types=1);

namespace App\Events\Briefings;

use App\Models\BriefingGeneration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BriefingGenerationCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  BriefingGeneration  $generation  The completed briefing generation
     */
    public function __construct(
        public BriefingGeneration $generation,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('workspace.%d.briefings', $this->generation->workspace_id)),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'briefing.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->generation->loadMissing('briefing');

        return [
            'generation_id' => $this->generation->id,
            'briefing_id' => $this->generation->briefing_id,
            'briefing_slug' => $this->generation->briefing?->slug,
            'status' => $this->generation->status->value,
            'has_achievements' => ! empty($this->generation->achievements),
        ];
    }
}
