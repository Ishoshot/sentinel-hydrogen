<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\Activity;
use App\Models\Annotation;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Invitation;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\UsageRecord;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class DeleteWorkspace
{
    /**
     * Delete a workspace and all associated data.
     *
     * Since foreign keys use NO ACTION, we must delete in dependency order.
     */
    public function handle(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace): void {
            $workspaceId = $workspace->id;

            // Delete in reverse dependency order (leaf nodes first)
            Annotation::where('workspace_id', $workspaceId)->delete();
            Finding::where('workspace_id', $workspaceId)->delete();
            Run::where('workspace_id', $workspaceId)->delete();
            ProviderKey::where('workspace_id', $workspaceId)->delete();
            RepositorySettings::where('workspace_id', $workspaceId)->delete();
            Repository::where('workspace_id', $workspaceId)->delete();
            Installation::where('workspace_id', $workspaceId)->delete();
            Connection::where('workspace_id', $workspaceId)->delete();
            Activity::where('workspace_id', $workspaceId)->delete();
            UsageRecord::where('workspace_id', $workspaceId)->delete();
            Subscription::where('workspace_id', $workspaceId)->delete();
            Invitation::where('workspace_id', $workspaceId)->delete();
            TeamMember::where('workspace_id', $workspaceId)->delete();
            Team::where('workspace_id', $workspaceId)->delete();

            $workspace->delete();
        });
    }
}
