<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Support\PolarOrderPaidPayloadResolver;
use App\Actions\Billing\Support\PolarOrderPaidPlanResolver;
use App\Actions\Billing\Support\PolarOrderPaidStateUpdater;
use App\Actions\Billing\Support\PolarOrderPaidWorkspaceResolver;
use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\Log;

final readonly class HandlePolarOrderPaid
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private PolarOrderPaidPayloadResolver $payloadResolver,
        private PolarOrderPaidWorkspaceResolver $workspaceResolver,
        private PolarOrderPaidPlanResolver $planResolver,
        private PolarOrderPaidStateUpdater $stateUpdater,
    ) {}

    /**
     * Handle a paid order webhook event.
     */
    public function handle(VerifiedPolarWebhook $webhook): void
    {
        $payload = $this->payloadResolver->resolve($webhook);

        if (! $payload instanceof Support\PolarOrderPaidPayload) {
            Log::warning('order.paid webhook missing data payload');

            return;
        }

        $workspaceResolution = $this->workspaceResolver->resolve($payload);
        if ($workspaceResolution->workspaceId === null) {
            Log::warning('Order paid but missing workspace_id', [
                'order_id' => $payload->order['id'] ?? null,
                'subscription_id' => $payload->subscriptionId,
            ]);

            return;
        }

        if (! $workspaceResolution->workspace instanceof \App\Models\Workspace) {
            Log::warning('Workspace not found for paid order', ['workspace_id' => $workspaceResolution->workspaceId]);

            return;
        }

        $workspace = $workspaceResolution->workspace;
        $plan = $this->planResolver->resolve($workspace, $payload, $workspaceResolution->planTier);

        if (! $plan instanceof \App\Models\Plan) {
            Log::warning('Could not resolve plan for paid order', [
                'workspace_id' => $workspace->id,
                'order_id' => $payload->order['id'] ?? null,
            ]);

            return;
        }

        $this->stateUpdater->apply($workspace, $plan, $payload);

        Log::info('Order paid - access granted', [
            'workspace_id' => $workspace->id,
            'plan_tier' => $plan->tier,
            'billing_interval' => $payload->billingInterval?->value,
            'order_id' => $payload->order['id'] ?? null,
        ]);
    }
}
