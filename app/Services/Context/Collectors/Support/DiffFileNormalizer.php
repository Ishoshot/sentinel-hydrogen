<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Support\MetadataExtractor;

/**
 * Normalizes GitHub PR file data and calculates change metrics.
 */
final readonly class DiffFileNormalizer
{
    /**
     * Normalize GitHub file data to a consistent structure.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    public function normalize(array $files): array
    {
        return array_map(function (array $file): array {
            $extractor = MetadataExtractor::from($file);

            return [
                'filename' => $extractor->string('filename'),
                'status' => $extractor->string('status', 'modified'),
                'additions' => $extractor->int('additions'),
                'deletions' => $extractor->int('deletions'),
                'changes' => $extractor->int('changes'),
                'patch' => $extractor->stringOrNull('patch'),
            ];
        }, $files);
    }

    /**
     * Calculate metrics from normalized files.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{files_changed: int, lines_added: int, lines_deleted: int}
     */
    public function calculateMetrics(array $files): array
    {
        return [
            'files_changed' => count($files),
            'lines_added' => array_sum(array_column($files, 'additions')),
            'lines_deleted' => array_sum(array_column($files, 'deletions')),
        ];
    }
}
