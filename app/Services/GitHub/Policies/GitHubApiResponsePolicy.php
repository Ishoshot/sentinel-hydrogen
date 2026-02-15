<?php

declare(strict_types=1);

namespace App\Services\GitHub\Policies;

final class GitHubApiResponsePolicy
{
    /**
     * @return array<string, mixed>
     */
    public function map(mixed $response): array
    {
        /** @var array<string, mixed> $response */
        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOfMaps(mixed $response): array
    {
        /** @var array<int, array<string, mixed>> $response */
        return $response;
    }

    /**
     * @return array<string, mixed>|string
     */
    public function mapOrString(mixed $response): array|string
    {
        /** @var array<string, mixed>|string $response */
        return $response;
    }

    /**
     * @return array{sha: string, url: string, tree: array<int, array{path: string, mode: string, type: string, sha: string, size?: int}>, truncated: bool}
     */
    public function repositoryTree(mixed $response): array
    {
        /** @var array{sha: string, url: string, tree: array<int, array{path: string, mode: string, type: string, sha: string, size?: int}>, truncated: bool} $response */
        return $response;
    }
}
