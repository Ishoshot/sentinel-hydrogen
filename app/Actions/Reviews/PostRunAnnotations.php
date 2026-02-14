<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Enums\Workspace\ActivityType;
use App\Models\Run;
use App\Services\Reviews\FormatRunAnnotations;
use App\Services\Reviews\PublishRunAnnotations;
use App\Services\Reviews\StoreRunAnnotations;
use Illuminate\Support\Facades\DB;

final readonly class PostRunAnnotations
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private LogActivity $logActivity,
        private FormatRunAnnotations $formatRunAnnotations,
        private PublishRunAnnotations $publishRunAnnotations,
        private StoreRunAnnotations $storeRunAnnotations,
    ) {}

    /**
     * Post review annotations to GitHub as PR comments.
     */
    public function handle(Run $run): int
    {
        if ($run->findings()->whereHas('annotations')->exists()) {
            return 0;
        }

        $run->loadMissing(['repository.installation', 'findings', 'workspace']);

        $context = $this->resolveContext($run);
        if ($context === null) {
            return 0;
        }

        ['owner' => $owner, 'repo' => $repo, 'pr_number' => $pullRequestNumber, 'installation_id' => $installationId, 'full_name' => $fullName] = $context;

        $annotationsConfig = $this->formatRunAnnotations->annotationsConfig($run);
        $eligibleFindings = $this->formatRunAnnotations->filterEligibleFindings($run, $annotationsConfig);
        $reviewBody = $this->formatRunAnnotations->buildReviewSummary($run);

        if ($eligibleFindings->isEmpty()) {
            $this->publishRunAnnotations->postSummaryOnly(
                $run,
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $reviewBody,
                $annotationsConfig
            );

            return 0;
        }

        $inlineComments = $this->formatRunAnnotations->buildInlineComments($eligibleFindings, $annotationsConfig);

        $metadata = $run->metadata ?? [];
        $commitId = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        $reviewResponse = $this->publishRunAnnotations->handle(
            $run,
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $reviewBody,
            $inlineComments,
            $commitId,
            $annotationsConfig
        );

        DB::transaction(function () use ($run, $eligibleFindings, $reviewResponse): void {
            $this->storeRunAnnotations->handle($run, $eligibleFindings, $reviewResponse);
        });

        $workspace = $run->workspace;
        if ($workspace !== null) {
            $this->logActivity->handle(
                workspace: $workspace,
                type: ActivityType::AnnotationsPosted,
                description: sprintf(
                    'Posted %d annotations for PR #%d in %s',
                    $eligibleFindings->count(),
                    $pullRequestNumber,
                    $fullName
                ),
                subject: $run,
                metadata: [
                    'annotations_count' => $eligibleFindings->count(),
                    'pull_request_number' => $pullRequestNumber,
                ],
            );
        }

        return $eligibleFindings->count();
    }

    /**
     * Resolve the GitHub context needed for posting annotations.
     *
     * @return array{owner: string, repo: string, pr_number: int, installation_id: int, full_name: string}|null
     */
    private function resolveContext(Run $run): ?array
    {
        $repository = $run->repository;
        $installation = $repository?->installation;

        if ($repository === null || $installation === null) {
            return null;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = $metadata['pull_request_number'] ?? null;

        if (! is_int($pullRequestNumber)) {
            return null;
        }

        $fullName = $repository->full_name;
        if ($fullName === null || ! str_contains($fullName, '/')) {
            return null;
        }

        [$owner, $repo] = explode('/', $fullName, 2);

        return [
            'owner' => $owner,
            'repo' => $repo,
            'pr_number' => $pullRequestNumber,
            'installation_id' => $installation->installation_id,
            'full_name' => $fullName,
        ];
    }
}
