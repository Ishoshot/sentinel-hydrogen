<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Services\Context\ContextBag;

/**
 * Marks files matching sensitive path patterns and records them in bag metadata.
 */
final readonly class SensitiveFileMarker
{
    /**
     * Create a new SensitiveFileMarker instance.
     */
    public function __construct(
        private ConfiguredPathInclusionDecider $inclusionDecider,
    ) {}

    /**
     * Mark files matching sensitive patterns and update bag metadata.
     *
     * @return int The number of files marked as sensitive
     */
    public function mark(ContextBag $bag, PathsConfig $pathsConfig): int
    {
        if ($pathsConfig->sensitive === []) {
            return 0;
        }

        $sensitiveFiles = [];
        $bag->files = array_map(function (array $file) use ($pathsConfig, &$sensitiveFiles): array {
            if ($this->inclusionDecider->isSensitive($file['filename'], $pathsConfig)) {
                $file['is_sensitive'] = true;
                $sensitiveFiles[] = $file['filename'];
            }

            return $file;
        }, $bag->files);

        if ($sensitiveFiles !== []) {
            $bag->metadata['sensitive_files'] = $sensitiveFiles;
        }

        return count($sensitiveFiles);
    }
}
