<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Run
 */
final class RunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repository_id' => $this->repository_id,
            'external_reference' => $this->external_reference,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'metrics' => $this->metrics,
            'policy_snapshot' => $this->policy_snapshot,
            'metadata' => $this->metadata,
            'repository' => new RepositoryResource($this->whenLoaded('repository')),
            'findings' => FindingResource::collection($this->whenLoaded('findings')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
