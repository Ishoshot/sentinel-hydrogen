<?php

declare(strict_types=1);

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class UpdateWorkspace
{
    /**
     * Update a workspace and its associated team name.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function execute(Workspace $workspace, string $name, ?array $settings = null): Workspace
    {
        return DB::transaction(function () use ($workspace, $name, $settings): Workspace {
            $workspace->update([
                'name' => $name,
                'settings' => $settings ?? $workspace->settings,
            ]);

            if ($workspace->team !== null) {
                $workspace->team->update(['name' => $name]);
            }

            return $workspace->refresh();
        });
    }
}
