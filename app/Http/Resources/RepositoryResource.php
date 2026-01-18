<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Repository
 */
final class RepositoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'github_id' => $this->github_id,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'owner' => $this->owner,
            'private' => $this->private,
            'default_branch' => $this->default_branch,
            'language' => $this->language,
            'description' => $this->description,
            'auto_review_enabled' => $this->hasAutoReviewEnabled(),
            'settings' => new RepositorySettingsResource($this->whenLoaded('settings')),
            'installation' => new InstallationResource($this->whenLoaded('installation')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
