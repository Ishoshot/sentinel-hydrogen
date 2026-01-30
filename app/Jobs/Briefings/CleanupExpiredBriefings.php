<?php

declare(strict_types=1);

namespace App\Jobs\Briefings;

use App\Enums\Queue\Queue;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CleanupExpiredBriefings implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::BriefingsDefault->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->cleanupExpiredGenerations();
        $this->cleanupExpiredShares();
    }

    /**
     * Cleanup expired briefing generations.
     */
    private function cleanupExpiredGenerations(): void
    {
        $disk = config('briefings.storage.disk', 'r2');
        $basePath = config('briefings.storage.path', 'briefings');

        $expiredGenerations = BriefingGeneration::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $deletedCount = 0;

        foreach ($expiredGenerations as $generation) {
            // Delete storage files
            if (! empty($generation->output_paths)) {
                $storagePath = sprintf(
                    '%s/%d/%d',
                    $basePath,
                    $generation->workspace_id,
                    $generation->id,
                );

                try {
                    Storage::disk($disk)->deleteDirectory($storagePath);
                } catch (Throwable $exception) {
                    Log::warning('Failed to delete briefing storage', [
                        'generation_id' => $generation->id,
                        'path' => $storagePath,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            // Delete related shares
            BriefingShare::query()
                ->where('briefing_generation_id', $generation->id)
                ->delete();

            // Delete the generation
            $generation->delete();
            $deletedCount++;
        }

        Log::info('Cleaned up expired briefing generations', [
            'deleted_count' => $deletedCount,
        ]);
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
