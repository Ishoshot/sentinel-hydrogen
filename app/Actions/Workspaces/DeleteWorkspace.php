<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class DeleteWorkspace
{
    /**
     * Delete a workspace and all associated data.
     */
    public function execute(Workspace $workspace): void
    {
        DB::transaction(function () use ($workspace): void {
            $workspace->delete();
        });
    }
}
