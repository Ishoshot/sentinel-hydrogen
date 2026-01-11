<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Activity
 */
final class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon' => $this->type->icon(),
            'type_category' => $this->type->category(),
            'description' => $this->description,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'subject_type' => $this->subject_type !== null ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'metadata' => $this->metadata,
            'is_system_action' => $this->isSystemAction(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
