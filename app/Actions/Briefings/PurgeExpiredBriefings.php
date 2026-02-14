<?php

declare(strict_types=1);

namespace App\Actions\Briefings;

use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Purge expired briefing generations and share tokens.
 */
final class PurgeExpiredBriefings
{
    /**
     * Execute the purge operation.
     */
    public function handle(): void
    {
        $this->cleanupExpiredGenerations();
        $this->cleanupExpiredShares();
    }

    /**
     * Cleanup expired briefing generations in memory-safe chunks.
     */
    private function cleanupExpiredGenerations(): void
    {
        $disk = config('briefings.storage.disk', 'r2');
        $basePath = config('briefings.storage.path', 'briefings');
        $chunkSize = (int) config('briefings.retention.cleanup_batch_size', 100);

        $totalExpired = BriefingGeneration::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->count();

        Log::info('Starting cleanup of expired briefing generations', [
            'total_expired' => $totalExpired,
            'chunk_size' => $chunkSize,
        ]);

        $deletedCount = 0;

        BriefingGeneration::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById($chunkSize, function (Collection $generations) use ($disk, $basePath, &$deletedCount): void {
                foreach ($generations as $generation) {
                    $this->deleteGenerationStorage($generation, $disk, $basePath);

                    BriefingShare::query()
                        ->where('briefing_generation_id', $generation->id)
                        ->delete();

                    $generation->delete();
                    $deletedCount++;
                }

                Log::info('Processed cleanup chunk', [
                    'chunk_size' => $generations->count(),
                    'deleted_so_far' => $deletedCount,
                ]);
            });

        Log::info('Cleaned up expired briefing generations', [
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Delete storage files for a generation.
     */
    private function deleteGenerationStorage(BriefingGeneration $generation, string $disk, string $basePath): void
    {
        if (empty($generation->output_paths)) {
            return;
        }

        $storagePath = sprintf(
            '%s/%d/%d',
            $basePath,
            $generation->workspace_id,
            $generation->id,
        );

        try {
            Storage::disk($disk)->deleteDirectory($storagePath);
        } catch (Throwable $throwable) {
            Log::warning('Failed to delete briefing storage', [
                'generation_id' => $generation->id,
                'path' => $storagePath,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Cleanup expired briefing shares.
     */
    private function cleanupExpiredShares(): void
    {
        $deletedCount = BriefingShare::query()
            ->where('expires_at', '<', now())
            ->delete();

        Log::info('Cleaned up expired briefing shares', [
            'deleted_count' => $deletedCount,
        ]);
    }
}
