<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Handlers;

use App\Actions\SentinelConfig\Resolvers\SentinelConfigFetchResultResolver;
use App\Models\Repository;
use Github\Exception\RuntimeException;
use Illuminate\Support\Facades\Log;

final readonly class SentinelConfigFetchExceptionHandler
{
    /**
     * Create a new handler instance.
     */
    public function __construct(private SentinelConfigFetchResultResolver $resultResolver) {}

    /**
     * Map a GitHub API exception to a sentinel config fetch result.
     *
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function handle(Repository $repository, RuntimeException $runtimeException): array
    {
        if ($runtimeException->getCode() === 404) {
            return $this->resultResolver->notFound();
        }

        Log::warning('Failed to fetch sentinel config', [
            'repository' => $repository->full_name,
            'error' => $runtimeException->getMessage(),
            'code' => $runtimeException->getCode(),
        ]);

        return $this->resultResolver->failed(
            sprintf('GitHub API error: %s', $runtimeException->getMessage())
        );
    }
}
