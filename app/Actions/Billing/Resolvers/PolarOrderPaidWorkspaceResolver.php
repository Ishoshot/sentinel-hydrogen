<?php

declare(strict_types=1);

namespace App\Actions\Billing\Resolvers;

use App\Actions\Billing\ValueObjects\PolarOrderPaidPayload;
use App\Actions\Billing\ValueObjects\PolarOrderPaidWorkspaceResolution;
use App\Models\Subscription;
use App\Models\Workspace;

final readonly class PolarOrderPaidWorkspaceResolver
{
    /**
     * Resolve workspace and effective plan tier from order payload.
     */
    public function resolve(PolarOrderPaidPayload $payload): PolarOrderPaidWorkspaceResolution
    {
        $workspaceId = $payload->workspaceId;
        $planTier = is_string($payload->planTier) ? $payload->planTier : null;

        if (! is_string($workspaceId) && ! is_int($workspaceId) && $payload->subscriptionId !== null) {
            $existingSubscription = Subscription::query()
                ->with('plan')
                ->where('polar_subscription_id', $payload->subscriptionId)
                ->first();

            if ($existingSubscription instanceof Subscription) {
                $workspaceId = $existingSubscription->workspace_id;

                if ($planTier === null && $existingSubscription->plan !== null) {
                    $planTier = $existingSubscription->plan->tier;
                }
            }
        }

        if (! is_string($workspaceId) && ! is_int($workspaceId)) {
            return new PolarOrderPaidWorkspaceResolution(
                workspace: null,
                planTier: $planTier,
                workspaceId: null,
            );
        }

        $resolvedWorkspaceId = (int) $workspaceId;
        $workspace = Workspace::query()->find($resolvedWorkspaceId);

        return new PolarOrderPaidWorkspaceResolution(
            workspace: $workspace,
            planTier: $planTier,
            workspaceId: $resolvedWorkspaceId,
        );
    }
}
