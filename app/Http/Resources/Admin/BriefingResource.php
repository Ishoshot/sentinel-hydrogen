<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Briefing
 */
final class BriefingResource extends JsonResource
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
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'target_roles' => $this->target_roles,
            'parameter_schema' => $this->parameter_schema,
            'prompt_path' => $this->prompt_path,
            'requires_ai' => $this->requires_ai,
            'eligible_plan_ids' => $this->eligible_plan_ids,
            'output_formats' => $this->output_formats,
            'is_schedulable' => $this->is_schedulable,
            'is_system' => $this->is_system,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'generations_count' => $this->whenCounted('generations'),
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
