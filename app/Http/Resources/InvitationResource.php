<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invitation
 */
final class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'workspace_id' => $this->workspace_id,
            'team_id' => $this->team_id,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'invited_by' => new UserResource($this->whenLoaded('invitedBy')),
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'is_expired' => $this->isExpired(),
            'is_accepted' => $this->isAccepted(),
            'is_pending' => $this->isPending(),
            'expires_at' => $this->expires_at->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
