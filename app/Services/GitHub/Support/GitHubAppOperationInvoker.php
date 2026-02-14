<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

use Closure;
use GrahamCampbell\GitHub\GitHubManager;

final readonly class GitHubAppOperationInvoker
{
    /**
     * Create a new app operation invoker instance.
     */
    public function __construct(
        private GitHubApiRequestExecutor $requestExecutor,
        private GitHubApiResponseGuard $responseGuard,
    ) {}

    /**
     * @param  Closure(GitHubManager): mixed  $operation
     * @return array<string, mixed>
     */
    public function map(string $operationName, Closure $operation): array
    {
        return $this->responseGuard->map($this->requestExecutor->runApp($operationName, $operation));
    }
}
