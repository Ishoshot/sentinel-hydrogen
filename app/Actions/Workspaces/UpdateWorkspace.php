<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final readonly class UpdateWorkspace
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Update a workspace and its associated team name.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function handle(Workspace $workspace, string $name, User $actor, ?array $settings = null): Workspace
    {
        $oldName = $workspace->name;

        return DB::transaction(function () use ($workspace, $name, $settings, $actor, $oldName): Workspace {
            $workspace->update([
                'name' => $name,
                'settings' => $settings ?? $workspace->settings,
            ]);

            if ($workspace->team !== null) {
                $workspace->team->update(['name' => $name]);
            }

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::WorkspaceUpdated,
                description: 'Workspace was updated',
                actor: $actor,
                subject: $workspace,
                metadata: $oldName !== $name ? ['old_name' => $oldName, 'new_name' => $name] : null,
            );

            return $workspace->refresh();
        });
    }
}
