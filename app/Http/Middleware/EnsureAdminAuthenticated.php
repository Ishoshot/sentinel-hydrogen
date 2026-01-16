<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the request is from an authenticated admin.
 */
final class EnsureAdminAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = $request->user('admin');

        if ($admin === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $admin->is_active) {
            return response()->json([
                'message' => 'Your admin account has been deactivated.',
            ], 403);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
