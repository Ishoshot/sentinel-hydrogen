<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Http\Resources\SubscriptionResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Display the current subscription for a workspace.
 */
final class ShowSubscriptionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $workspace->loadMissing('plan');

        return response()->json([
            'data' => new SubscriptionResource($workspace),
        ]);
    }
}
