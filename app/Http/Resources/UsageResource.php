<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property-read \App\Models\UsageRecord $resource
 */
final class UsageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'workspace_id' => $this->resource->workspace_id,
            'period_start' => $this->resource->period_start?->toDateString(),
            'period_end' => $this->resource->period_end?->toDateString(),
            'runs_count' => $this->resource->runs_count,
            'findings_count' => $this->resource->findings_count,
            'annotations_count' => $this->resource->annotations_count,
        ];
    }
}
