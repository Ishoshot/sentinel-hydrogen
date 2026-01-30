<?php

declare(strict_types=1);

namespace App\Events\GitHub;

use App\Enums\Queue\Queue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ConfigPullRequestCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public int $workspaceId,
        public int $repositoryId,
        public string $repositoryName,
        public string $prUrl,
    ) {
        $this->onQueue(Queue::Notifications->value);
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(sprintf('workspace.%d.repositories', $this->workspaceId)),
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'config-pr.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'repository_id' => $this->repositoryId,
            'repository_name' => $this->repositoryName,
            'pr_url' => $this->prUrl,
        ];
    }
}
