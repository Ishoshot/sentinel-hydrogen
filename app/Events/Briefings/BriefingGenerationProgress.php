<?php

declare(strict_types=1);

namespace App\Events\Briefings;

use App\Enums\Queue\Queue;
use App\Models\BriefingGeneration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BriefingGenerationProgress implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  BriefingGeneration  $generation  The briefing generation being processed
     * @param  int  $progress  Current progress percentage (0-100)
     * @param  string  $message  Status message describing current operation
     */
    public function __construct(
        public BriefingGeneration $generation,
        public int $progress,
        public string $message,
    ) {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

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
        return 'briefing.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'generation_id' => $this->generation->id,
            'briefing_id' => $this->generation->briefing_id,
            'progress' => $this->progress,
            'message' => $this->message,
        ];
    }
}
