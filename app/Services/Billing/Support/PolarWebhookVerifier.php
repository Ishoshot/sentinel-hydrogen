<?php

declare(strict_types=1);

namespace App\Services\Billing\Support;

use App\Services\Billing\ValueObjects\VerifiedPolarWebhook;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use StandardWebhooks\Exception\WebhookVerificationException;
use StandardWebhooks\Webhook;

final class PolarWebhookVerifier
{
    /**
     * Verify and parse a Polar webhook payload.
     *
     * @param  array{webhook-id: string, webhook-signature: string, webhook-timestamp: string}  $headers
     */
    public function verify(string $payload, array $headers): VerifiedPolarWebhook
    {
        $secret = (string) config('services.polar.webhook_secret');

        if ($secret === '') {
            Log::error('Polar webhook secret not configured');

            throw new RuntimeException('Polar webhook secret is not configured.');
        }

        $signingSecret = str_starts_with($secret, 'whsec_')
            ? $secret
            : 'whsec_'.base64_encode($secret);

        try {
            $webhook = new Webhook($signingSecret);
            $webhook->verify($payload, $headers);
        } catch (WebhookVerificationException $webhookVerificationException) {
            Log::warning('Invalid Polar webhook signature received', [
                'error' => $webhookVerificationException->getMessage(),
            ]);

            throw new RuntimeException('Invalid Polar webhook signature.', $webhookVerificationException->getCode(), $webhookVerificationException);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            Log::warning('Invalid Polar webhook payload format');

            throw new RuntimeException('Invalid Polar webhook payload.');
        }

        /** @var array<string, mixed> $event */
        return VerifiedPolarWebhook::fromArray($event);
    }
}
