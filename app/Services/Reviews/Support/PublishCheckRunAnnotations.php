<?php

declare(strict_types=1);

namespace App\Services\Reviews\Support;

use App\Models\Run;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;

final readonly class PublishCheckRunAnnotations
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
    ) {}

    /**
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    public function publish(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        string $reviewBody,
        array $inlineComments,
        ?string $commitId,
    ): array {
        if ($commitId === null) {
            Log::warning('PostRunAnnotations: Cannot create check run without commit SHA', [
                'run_id' => $run->id,
            ]);

            return [];
        }

        $annotations = array_map(fn (array $comment): array => [
            'path' => $comment['path'],
            'start_line' => $comment['line'],
            'end_line' => $comment['line'],
            'annotation_level' => 'warning',
            'message' => strip_tags($comment['body']),
        ], $inlineComments);

        return $this->gitHubApiService->createCheckRun(
            $installationId,
            $owner,
            $repo,
            'Sentinel Code Review',
            $commitId,
            'completed',
            $inlineComments !== [] ? 'neutral' : 'success',
            $reviewBody,
            $annotations
        );
    }

    /**
     * Publish only a summary check run when no inline annotations are posted.
     */
    public function postSummaryOnly(
        int $installationId,
        string $owner,
        string $repo,
        string $summary,
        ?string $commitId,
    ): void {
        if ($commitId === null) {
            return;
        }

        $this->gitHubApiService->createCheckRun(
            $installationId,
            $owner,
            $repo,
            'Sentinel Code Review',
            $commitId,
            'completed',
            'success',
            $summary,
            []
        );
    }
}
