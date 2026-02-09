<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SlackIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin SlackIntegration
 */
final class SlackIntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'bot_user_id' => $this->bot_user_id,
            'slack_team_id' => $this->slack_team_id,
            'team_name' => $this->team_name,
            'channel_id' => $this->channel_id,
            'channel_name' => $this->channel_name,
            'has_channel' => $this->channel_id !== null,
            'is_active' => $this->is_active,
            'is_connected' => $this->is_active && $this->connected_at !== null,
            'is_fully_configured' => $this->isFullyConfigured(),
            'connected_at' => $this->connected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
