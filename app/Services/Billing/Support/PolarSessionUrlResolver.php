<?php

declare(strict_types=1);

namespace App\Services\Billing\Support;

use App\Models\Workspace;
use App\Services\Logging\LogContext;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class PolarSessionUrlResolver
{
    /**
     * Resolve checkout URL from a Polar API response.
     *
     * @param  array<string, mixed>  $data
     */
    public function checkout(Workspace $workspace, array $data): string
    {
        $checkoutUrl = $data['url'] ?? null;

        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            Log::error('Polar API returned no checkout URL', LogContext::fromWorkspace($workspace));

            throw new RuntimeException('Polar API did not return a checkout URL.');
        }

        return $checkoutUrl;
    }

    /**
     * Resolve customer portal URL from a Polar API response.
     *
     * @param  array<string, mixed>  $data
     */
    public function customerPortal(Workspace $workspace, array $data): string
    {
        $portalUrl = $data['customer_portal_url'] ?? null;

        if (! is_string($portalUrl) || $portalUrl === '') {
            Log::error('Polar API returned no portal URL', LogContext::fromWorkspace($workspace));

            throw new RuntimeException('Polar API did not return a customer portal URL.');
        }

        return $portalUrl;
    }
}
