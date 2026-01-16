<?php

declare(strict_types=1);

namespace App\Http\Resources\Briefings;

use App\Models\BriefingSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin BriefingSubscription
 */
final class BriefingSubscriptionResource extends JsonResource
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
            'briefing_id' => $this->briefing_id,
            'briefing' => $this->when(
                $this->relationLoaded('briefing'),
                fn (): BriefingResource => new BriefingResource($this->briefing)
            ),
            'schedule_preset' => $this->schedule_preset->value,
            'schedule_day' => $this->schedule_day,
            'schedule_hour' => $this->schedule_hour,
            'parameters' => $this->parameters,
            'delivery_channels' => $this->delivery_channels,
            'is_active' => $this->is_active,
            'last_generated_at' => $this->last_generated_at?->toIso8601String(),
            'next_scheduled_at' => $this->next_scheduled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
