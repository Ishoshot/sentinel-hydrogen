<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Finding;
use App\Models\Run;
use App\Services\Reviews\ValueObjects\ReviewFinding;

final class PersistReviewFindings
{
    /**
     * @param  array<int, ReviewFinding>  $findings
     */
    public function handle(Run $run, array $findings): void
    {
        foreach ($findings as $finding) {
            $this->createFinding($run, $finding);
        }
    }

    /**
     * Persist a finding when it has not already been stored for the run.
     */
    private function createFinding(Run $run, ReviewFinding $finding): void
    {
        $findingHash = $this->generateFindingHash($finding);

        $duplicateExists = Finding::query()
            ->where('run_id', $run->id)
            ->where('finding_hash', $findingHash)
            ->exists();

        if ($duplicateExists) {
            return;
        }

        $metadata = array_filter([
            'impact' => $finding->impact !== '' ? $finding->impact : null,
            'current_code' => $finding->currentCode,
            'replacement_code' => $finding->replacementCode,
            'explanation' => $finding->explanation,
            'references' => $finding->references !== [] ? $finding->references : null,
        ], fn (mixed $value): bool => $value !== null);

        Finding::query()->create([
            'run_id' => $run->id,
            'finding_hash' => $findingHash,
            'workspace_id' => $run->workspace_id,
            'severity' => $finding->severity->value,
            'category' => $finding->category->value,
            'title' => $finding->title,
            'description' => $finding->description,
            'file_path' => $finding->filePath,
            'line_start' => $finding->lineStart,
            'line_end' => $finding->lineEnd,
            'confidence' => $finding->confidence,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Generate a deterministic hash used to de-duplicate findings per run.
     */
    private function generateFindingHash(ReviewFinding $finding): string
    {
        $payload = [
            'severity' => $finding->severity->value,
            'category' => $finding->category->value,
            'title' => $finding->title,
            'description' => $finding->description,
            'file_path' => $finding->filePath ?? '',
            'line_start' => $finding->lineStart,
            'line_end' => $finding->lineEnd,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $encoded = serialize($payload);
        }

        return hash('sha256', $encoded);
    }
}
