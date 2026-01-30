<?php

declare(strict_types=1);

namespace App\Http\Controllers\Subscriptions;

use App\Models\Workspace;
use App\Services\Billing\PolarBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Generate a customer portal session URL for managing billing.
 */
final class SubscriptionPortalController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Workspace $workspace, PolarBillingService $billingService): JsonResponse
    {
        Gate::authorize('manageSubscription', $workspace);

        if (! $billingService->isConfigured()) {
            return response()->json([
                'message' => 'Polar billing is not configured.',
            ], 422);
        }

        $portalUrl = $billingService->createCustomerPortalSession($workspace);

        return response()->json([
            'data' => [
                'portal_url' => $portalUrl,
            ],
            'message' => 'Polar customer portal session created.',
        ]);
    }
}
