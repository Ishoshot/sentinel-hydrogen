<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWorkspaceAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->belongsToWorkspace($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        app()->instance('current_workspace', $workspace);
        app()->instance('current_workspace_id', $workspace->id);

        return $next($request);
    }
}
