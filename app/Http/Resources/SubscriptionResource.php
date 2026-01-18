<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @property-read \App\Models\Workspace $resource
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $plan = $this->resource->plan;

        return [
            'workspace_id' => $this->resource->id,
            'plan' => $plan ? new PlanResource($plan) : null,
            'status' => $this->resource->subscription_status?->value,
            'trial_ends_at' => $this->resource->trial_ends_at?->toISOString(),
        ];
    }
}
