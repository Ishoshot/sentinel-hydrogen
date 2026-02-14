<?php

declare(strict_types=1);

namespace App\Actions\SentinelConfig\Factories;

final class SentinelConfigFetchResultFactory
{
    /**
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function found(?string $content, ?string $sha, ?string $error): array
    {
        return [
            'found' => true,
            'content' => $content,
            'sha' => $sha,
            'error' => $error,
        ];
    }

    /**
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function notFound(): array
    {
        return [
            'found' => false,
            'content' => null,
            'sha' => null,
            'error' => null,
        ];
    }

    /**
     * @return array{found: bool, content: ?string, sha: ?string, error: ?string}
     */
    public function failed(string $error): array
    {
        return [
            'found' => false,
            'content' => null,
            'sha' => null,
            'error' => $error,
        ];
    }
}
