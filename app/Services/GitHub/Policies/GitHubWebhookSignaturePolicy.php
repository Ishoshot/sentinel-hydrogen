<?php

declare(strict_types=1);

namespace App\Services\GitHub\Policies;

final class GitHubWebhookSignaturePolicy
{
    /**
     * Verify a GitHub webhook payload signature.
     */
    public function verify(string $payload, string $signature): bool
    {
        $secret = config('github.webhook_secret');

        if (empty($secret)) {
            return false;
        }

        /** @var string $secretString */
        $secretString = $secret;
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secretString);

        return hash_equals($expectedSignature, $signature);
    }
}
