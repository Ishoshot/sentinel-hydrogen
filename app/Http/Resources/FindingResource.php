<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Finding
 */
final class FindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_id' => $this->run_id,
            // 'finding_hash' => $this->finding_hash,
            'severity' => $this->severity,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'file_path' => $this->file_path,
            'line_start' => $this->line_start,
            'line_end' => $this->line_end,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
            'annotations' => AnnotationResource::collection($this->whenLoaded('annotations')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
