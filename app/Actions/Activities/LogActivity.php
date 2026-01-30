<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Enums\Workspace\ActivityType;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

final class LogActivity
{
    /**
     * Log an activity for a workspace.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Workspace $workspace,
        ActivityType $type,
        string $description,
        ?User $actor = null,
        ?Model $subject = null,
        ?array $metadata = null,
    ): Activity {
        return Activity::create([
            'workspace_id' => $workspace->id,
            'actor_id' => $actor?->id,
            'type' => $type->value,
            'subject_type' => $subject instanceof Model ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
