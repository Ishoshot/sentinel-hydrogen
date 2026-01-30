<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('workspace.{workspaceId}.briefings', function (User $user, int $workspaceId) {
    $workspace = Workspace::find($workspaceId);

    if ($workspace === null) {
        return false;
    }

    return $user->belongsToWorkspace($workspace);
});

Broadcast::channel('workspace.{workspaceId}.repositories', function (User $user, int $workspaceId) {
    $workspace = Workspace::find($workspaceId);

    if ($workspace === null) {
        return false;
    }

    return $user->belongsToWorkspace($workspace);
});
