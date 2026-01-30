<?php

declare(strict_types=1);

namespace App\Actions\Webhooks;

use App\Enums\Billing\Partner;
use App\Models\IncomingWebhook;

final class RecordIncomingWebhook
{
    /**
     * Record an incoming webhook from a partner.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handle(
        Partner $partner,
        array $payload,
        array $headers = [],
        ?string $webhookId = null,
        ?string $eventType = null,
        ?string $ipAddress = null,
    ): IncomingWebhook {
        return IncomingWebhook::query()->create([
            'partner' => $partner,
            'webhook_id' => $webhookId,
            'event_type' => $eventType,
            'payload' => $payload,
            'headers' => $headers,
            'ip_address' => $ipAddress,
        ]);
    }
}
