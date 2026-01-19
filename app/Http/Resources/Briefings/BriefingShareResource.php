<?php

declare(strict_types=1);

namespace App\Http\Resources\Briefings;

use App\Models\BriefingShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin BriefingShare
 */
final class BriefingShareResource extends JsonResource
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
            'token' => $this->token,
            'share_url' => route('briefings.share.show', $this->token),
            'is_password_protected' => $this->isPasswordProtected(),
            'access_count' => $this->access_count,
            'max_accesses' => $this->max_accesses,
            'is_active' => $this->is_active,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
