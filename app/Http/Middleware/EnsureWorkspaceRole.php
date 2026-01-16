<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TeamRole;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureWorkspaceRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $workspace = $request->route('workspace');

        if (! $workspace instanceof Workspace) {
            abort(404);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $userRole = $user->roleInWorkspace($workspace);

        if ($userRole === null) {
            abort(403, 'You are not a member of this workspace.');
        }

        $allowedRoles = array_map(
            TeamRole::from(...),
            $roles
        );

        if (! in_array($userRole, $allowedRoles, true)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
