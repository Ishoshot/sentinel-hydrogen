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
        $generatedBy = $this->relationLoaded('generatedBy') ? $this->generatedBy : null;
        $hasGeneratedBy = $generatedBy !== null;
        $generatedByPayload = $hasGeneratedBy
            ? [
                'id' => $generatedBy->id,
                'name' => $generatedBy->name,
                'email' => $generatedBy->email,
                'avatar_url' => $generatedBy->avatar_url,
            ]
            : [];

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'briefing_id' => $this->briefing_id,
            'generated_by_id' => $this->generated_by_id,
            'briefing' => $this->when(
                $this->relationLoaded('briefing'),
                fn (): BriefingResource => new BriefingResource($this->briefing)
            ),
            'generated_by' => $this->when(
                $hasGeneratedBy,
                fn (): array => $generatedByPayload
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
            'ai_generation' => $this->when(
                $this->status->value === 'completed' && isset($this->metadata['ai_telemetry']),
                fn (): array => $this->formatAiGeneration()
            ),
            'error_message' => $this->when(
                $this->status->value === 'failed',
                fn () => $this->error_message
            ),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format AI generation telemetry for the response.
     *
     * @return array<string, mixed>
     */
    private function formatAiGeneration(): array
    {
        $telemetry = $this->metadata['ai_telemetry'] ?? [];

        return [
            'provider' => $telemetry['provider'] ?? null,
            'model' => $telemetry['model'] ?? null,
            'duration_ms' => $telemetry['duration_ms'] ?? null,
        ];
    }
}
