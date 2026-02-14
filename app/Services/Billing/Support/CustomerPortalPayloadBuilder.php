<?php

declare(strict_types=1);

namespace App\Services\Billing\Support;

use App\Models\Workspace;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves the customer ID and builds the payload for a Polar customer portal session.
 */
final readonly class CustomerPortalPayloadBuilder
{
    /**
     * Resolve the customer ID and build the portal session payload.
     *
     * @return array<string, string>
     *
     * @throws InvalidArgumentException if the workspace has no Polar customer ID
     */
    public function build(Workspace $workspace, ?string $returnUrl): array
    {
        $subscription = $workspace->subscriptions()->latest()->first();
        $customerId = $subscription?->polar_customer_id;

        if ($customerId === null || $customerId === '') {
            Log::warning('Workspace missing Polar customer ID', LogContext::fromWorkspace($workspace));

            throw new InvalidArgumentException('Workspace does not have a Polar customer ID.');
        }

        $payload = ['customer_id' => $customerId];

        if ($returnUrl !== null && $returnUrl !== '') {
            $payload['return_url'] = $returnUrl;
        }

        return $payload;
    }
}
