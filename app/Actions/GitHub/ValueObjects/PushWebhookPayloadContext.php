<?php

declare(strict_types=1);

namespace App\Actions\GitHub\ValueObjects;

final readonly class PushWebhookPayloadContext
{
    /**
     * Create a new push webhook payload context.
     *
     * @param  array<string, mixed>  $logContext
     */
    public function __construct(
        public string $ref,
        public int|string|null $installationId,
        public int|string|null $repositoryId,
        public string $repositoryFullName,
        public array $logContext,
    ) {}
}
