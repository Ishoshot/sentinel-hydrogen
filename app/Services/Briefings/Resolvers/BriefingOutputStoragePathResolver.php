<?php

declare(strict_types=1);

namespace App\Services\Briefings\Resolvers;

use App\Models\BriefingGeneration;

final class BriefingOutputStoragePathResolver
{
    /**
     * Resolve the configured storage disk.
     */
    public function disk(): string
    {
        return (string) config('briefings.storage.disk', 'r2');
    }

    /**
     * Resolve the storage path for a generated briefing artifact.
     */
    public function resolve(BriefingGeneration $generation, string $filename): string
    {
        $basePath = (string) config('briefings.storage.path', 'briefings');

        return sprintf('%s/%d/%d/%s', $basePath, $generation->workspace_id, $generation->id, $filename);
    }
}
