<?php

declare(strict_types=1);

namespace App\Http\Resources\Briefings;

use App\Models\BriefingGeneration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin BriefingGeneration
 */
final class BriefingGenerationResource extends JsonResource
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
            'status' => $this->status->value,
            'progress' => $this->progress,
            'progress_message' => $this->progress_message,
            'parameters' => $this->parameters,
            'narrative' => $this->when(
                $this->status->value === 'completed',
                fn () => $this->narrative
            ),
            'structured_data' => $this->when(
                $this->status->value === 'completed',
                fn () => $this->structured_data
            ),
            'achievements' => $this->when(
                $this->status->value === 'completed',
                fn () => $this->achievements
            ),
            'excerpts' => $this->when(
                $this->status->value === 'completed',
                fn () => $this->excerpts
            ),
            'output_formats' => $this->when(
                $this->status->value === 'completed',
                fn (): array => array_keys($this->output_paths ?? [])
            ),
            'error_message' => $this->when(
                $this->status->value === 'failed',
                fn () => $this->error_message
            ),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
