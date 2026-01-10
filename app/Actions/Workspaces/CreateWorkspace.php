<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateWorkspace
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
    ) {}

    /**
     * Create a new workspace with its associated team and owner membership.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function handle(User $owner, string $name, ?array $settings = null): Workspace
    {
        return DB::transaction(function () use ($owner, $name, $settings): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'owner_id' => $owner->id,
                'settings' => $settings,
            ]);

            $team = Team::create([
                'name' => $name,
                'workspace_id' => $workspace->id,
            ]);

            TeamMember::create([
                'user_id' => $owner->id,
                'team_id' => $team->id,
                'workspace_id' => $workspace->id,
                'role' => TeamRole::Owner,
                'joined_at' => now(),
            ]);

            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::WorkspaceCreated,
                description: sprintf('Workspace "%s" was created', $name),
                actor: $owner,
                subject: $workspace,
            );

            return $workspace->refresh();
        });
    }

    /**
     * Generate a unique slug for the workspace.
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = sprintf('%s-%d', $baseSlug, $counter);
            $counter++;
        }

        return $slug;
    }
}
