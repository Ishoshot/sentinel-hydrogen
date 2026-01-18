<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\RepositorySettings
 */
final class RepositorySettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auto_review_enabled' => $this->auto_review_enabled,

            // Sentinel config from .sentinel/config.yaml
            'sentinel_config' => $this->sentinel_config,
            'config_synced_at' => $this->config_synced_at?->toISOString(),
            'config_error' => $this->config_error,
            'has_sentinel_config' => $this->sentinel_config !== null,
            'has_config_error' => $this->hasConfigError(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
