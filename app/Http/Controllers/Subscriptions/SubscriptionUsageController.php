<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Actions\Subscriptions\GetSubscriptionUsage;
use App\Http\Resources\UsageResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Display usage statistics for the current billing period.
 */
final class SubscriptionUsageController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Workspace $workspace,
        GetSubscriptionUsage $getSubscriptionUsage,
    ): JsonResponse {
        Gate::authorize('view', $workspace);

        $usage = $getSubscriptionUsage->handle($workspace);

        return response()->json([
            'data' => new UsageResource($usage),
        ]);
    }
}
