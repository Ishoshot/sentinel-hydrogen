<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Factories;

use App\Services\CodeIndexing\ValueObjects\CodeIndexScope;

/**
 * Assembles persistence payloads for code-index records.
 */
final readonly class CodeIndexingPayloadFactory
{
    /**
     * @param  array<string, mixed>|null  $structure
     * @return array{
     *   scope_type: string,
     *   scope_ref: string,
     *   pull_request_number: int|null,
     *   head_sha: string|null,
     *   commit_sha: string,
     *   file_type: string,
     *   content: string,
     *   structure: array<string, mixed>|null,
     *   metadata: array{lines: int, size: int},
     *   indexed_at: \Illuminate\Support\Carbon
     * }
     */
    public function build(string $commitSha, string $filePath, string $content, ?array $structure, ?CodeIndexScope $scope = null): array
    {
        $resolvedScope = $scope ?? CodeIndexScope::baseline();
        $fileType = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'txt';

        return [
            'scope_type' => $resolvedScope->type->value,
            'scope_ref' => $resolvedScope->ref,
            'pull_request_number' => $resolvedScope->pullRequestNumber,
            'head_sha' => $resolvedScope->headSha,
            'commit_sha' => $commitSha,
            'file_type' => $fileType,
            'content' => $content,
            'structure' => $structure,
            'metadata' => [
                'lines' => mb_substr_count($content, "\n") + 1,
                'size' => mb_strlen($content),
            ],
            'indexed_at' => now(),
        ];
    }
}
