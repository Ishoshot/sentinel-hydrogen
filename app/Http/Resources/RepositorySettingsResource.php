<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\RepositorySettings
 */
final class RepositorySettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auto_review_enabled' => $this->auto_review_enabled,
            'review_rules' => $this->review_rules,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
