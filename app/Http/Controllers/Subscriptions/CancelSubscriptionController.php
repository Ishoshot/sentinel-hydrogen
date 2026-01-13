<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Actions\Subscriptions\CancelSubscription;
use App\Http\Resources\SubscriptionResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cancel a workspace subscription and downgrade to Foundation plan.
 */
final class CancelSubscriptionController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Workspace $workspace,
        CancelSubscription $cancelSubscription,
    ): JsonResponse {
        Gate::authorize('manageSubscription', $workspace);

        $cancelSubscription->handle($workspace, $request->user());

        return response()->json([
            'data' => new SubscriptionResource($workspace->refresh()->load('plan')),
            'message' => 'Subscription canceled and downgraded to Foundation plan.',
        ]);
    }
}
