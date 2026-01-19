<?php

declare(strict_types=1);

namespace App\Http\Resources\Briefings;

use App\Models\Briefing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Briefing
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
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'target_roles' => $this->target_roles,
            'parameter_schema' => $this->parameter_schema,
            'requires_ai' => $this->requires_ai,
            'output_formats' => $this->output_formats,
            'is_schedulable' => $this->is_schedulable,
            'is_system' => $this->is_system,
        ];
    }
}
