<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Factories;

/**
 * Assembles persistence payloads for code-index records.
 */
final readonly class CodeIndexingPayloadFactory
{
    /**
     * @param  array<string, mixed>|null  $structure
     * @return array{
     *   commit_sha: string,
     *   file_type: string,
     *   content: string,
     *   structure: array<string, mixed>|null,
     *   metadata: array{lines: int, size: int},
     *   indexed_at: \Illuminate\Support\Carbon
     * }
     */
    public function build(string $commitSha, string $filePath, string $content, ?array $structure): array
    {
        $fileType = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'txt';

        return [
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
