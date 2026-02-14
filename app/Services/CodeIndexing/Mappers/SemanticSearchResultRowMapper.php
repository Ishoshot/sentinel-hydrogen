<?php

declare(strict_types=1);

namespace App\Services\CodeIndexing\Support;

use stdClass;

final readonly class SemanticSearchResultRowMapper
{
    /**
     * @return array{file_path: string, content: string, score: float, metadata: array<string, mixed>}
     */
    public function map(stdClass $row): array
    {
        $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : $row->metadata;
        $distance = is_numeric($row->distance) ? (float) $row->distance : 0.5;

        return [
            'file_path' => (string) $row->file_path,
            'content' => (string) $row->content,
            'score' => 1 - $distance,
            'metadata' => [
                'file_type' => $row->file_type,
                'chunk_type' => $row->chunk_type,
                'symbol_name' => $row->symbol_name,
                'match_type' => 'semantic',
                ...$this->normalizeMetadata($metadata),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
