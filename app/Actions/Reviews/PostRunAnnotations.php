<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\AnnotationType;
use App\Enums\ProviderType;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Provider;
use App\Models\Run;
use App\Services\GitHub\GitHubApiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Posts review annotations to GitHub as PR review comments.
 */
final readonly class PostRunAnnotations
{
    private const array SEVERITY_ORDER = [
        'info' => 1,
        'low' => 2,
        'medium' => 3,
        'high' => 4,
        'critical' => 5,
    ];

    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiService $gitHubApiService,
        private LogActivity $logActivity,
    ) {}

    /**
     * Post review annotations to GitHub as PR comments.
     */
    public function handle(Run $run): int
    {
        $run->loadMissing(['repository.installation', 'findings', 'workspace']);

        $repository = $run->repository;
        $installation = $repository?->installation;

        if ($repository === null || $installation === null) {
            return 0;
        }

        $metadata = $run->metadata ?? [];
        $pullRequestNumber = $metadata['pull_request_number'] ?? null;

        if ($pullRequestNumber === null || ! is_int($pullRequestNumber)) {
            return 0;
        }

        $fullName = $repository->full_name;
        if ($fullName === null || ! str_contains($fullName, '/')) {
            return 0;
        }

        [$owner, $repo] = explode('/', $fullName, 2);
        $installationId = $installation->installation_id;

        $eligibleFindings = $this->filterEligibleFindings($run);

        if ($eligibleFindings->isEmpty()) {
            $this->postSummaryOnly($run, $installationId, $owner, $repo, $pullRequestNumber);

            return 0;
        }

        $reviewBody = $this->buildReviewSummary($run);
        $inlineComments = $this->buildInlineComments($eligibleFindings);

        /** @var array<string, mixed> $reviewResponse */
        $reviewResponse = $this->gitHubApiService->createPullRequestReview(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $reviewBody,
            'COMMENT',
            $inlineComments
        );

        DB::transaction(function () use ($run, $eligibleFindings, $reviewResponse): void {
            $this->storeAnnotations($run, $eligibleFindings, $reviewResponse);
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
     * Post a summary-only review when there are no eligible findings.
     */
    private function postSummaryOnly(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber
    ): void {
        $summary = $this->buildReviewSummary($run);

        $this->gitHubApiService->createPullRequestReview(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $summary,
            'COMMENT',
            []
        );
    }

    /**
     * Filter findings that are eligible for posting as comments.
     *
     * @return Collection<int, Finding>
     */
    private function filterEligibleFindings(Run $run): Collection
    {
        $policy = $run->policy_snapshot ?? [];

        $severityThresholds = is_array($policy['severity_thresholds'] ?? null) ? $policy['severity_thresholds'] : [];
        $commentLimits = is_array($policy['comment_limits'] ?? null) ? $policy['comment_limits'] : [];

        $severityThreshold = is_string($severityThresholds['comment'] ?? null) ? $severityThresholds['comment'] : 'medium';
        $maxComments = is_int($commentLimits['max_inline_comments'] ?? null) ? $commentLimits['max_inline_comments'] : 10;

        $minSeverity = self::SEVERITY_ORDER[$severityThreshold] ?? 3;

        return $run->findings
            ->filter(function (Finding $finding) use ($minSeverity): bool {
                $findingSeverity = self::SEVERITY_ORDER[$finding->severity] ?? 0;

                return $findingSeverity >= $minSeverity
                    && $finding->file_path !== null
                    && $finding->line_start !== null;
            })
            ->sortByDesc(fn (Finding $finding): int => self::SEVERITY_ORDER[$finding->severity] ?? 0)
            ->take($maxComments);
    }

    /**
     * Build the review summary body.
     */
    private function buildReviewSummary(Run $run): string
    {
        $metadata = $run->metadata ?? [];
        $summary = is_array($metadata['review_summary'] ?? null) ? $metadata['review_summary'] : [];

        $overview = is_string($summary['overview'] ?? null) ? $summary['overview'] : 'Review completed.';
        $riskLevel = is_string($summary['risk_level'] ?? null) ? $summary['risk_level'] : 'low';
        $recommendations = is_array($summary['recommendations'] ?? null) ? $summary['recommendations'] : [];

        $body = "## Sentinel Review Summary\n\n";
        $body .= '**Risk Level:** '.ucfirst($riskLevel)."\n\n";
        $body .= $overview."\n";

        $findingsCount = $run->findings()->count();
        if ($findingsCount > 0) {
            $body .= "\n**Findings:** {$findingsCount} issue(s) identified.\n";
        }

        if ($recommendations !== []) {
            $body .= "\n### Recommendations\n\n";
            foreach ($recommendations as $rec) {
                if (is_string($rec)) {
                    $body .= sprintf('- %s%s', $rec, PHP_EOL);
                }
            }
        }

        return $body."\n---\n*Generated by [Sentinel](https://sentinel.dev)*";
    }

    /**
     * Build inline comments for eligible findings.
     *
     * @param  Collection<int, Finding>  $findings
     * @return array<int, array{path: string, position: int, body: string}>
     */
    private function buildInlineComments(Collection $findings): array
    {
        $comments = [];

        foreach ($findings as $finding) {
            if ($finding->file_path === null) {
                continue;
            }

            if ($finding->line_start === null) {
                continue;
            }

            $comments[] = [
                'path' => $finding->file_path,
                'position' => $finding->line_start,
                'body' => $this->formatFindingComment($finding),
            ];
        }

        return $comments;
    }

    /**
     * Format a single finding as a comment body.
     */
    private function formatFindingComment(Finding $finding): string
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $finding->metadata ?? [];

        $severityBadge = match ($finding->severity) {
            'critical' => '**:red_circle: Critical**',
            'high' => '**:orange_circle: High**',
            'medium' => '**:yellow_circle: Medium**',
            'low' => '**:white_circle: Low**',
            default => '**:blue_circle: Info**',
        };

        $body = "{$severityBadge} | `{$finding->category}`\n\n";
        $body .= "### {$finding->title}\n\n";
        $body .= $finding->description.PHP_EOL;

        $suggestion = isset($metadata['suggestion']) && is_string($metadata['suggestion']) ? $metadata['suggestion'] : null;
        if ($suggestion !== null && $suggestion !== '') {
            $body .= "\n**Suggestion:** {$suggestion}\n";
        }

        $rationale = isset($metadata['rationale']) && is_string($metadata['rationale']) ? $metadata['rationale'] : null;
        if ($rationale !== null && $rationale !== '') {
            $body .= "\n_Rationale: {$rationale}_\n";
        }

        $confidence = $finding->confidence;
        if ($confidence !== null) {
            $confidencePercent = (int) round($confidence * 100);
            $body .= "\n`Confidence: {$confidencePercent}%`";
        }

        return $body;
    }

    /**
     * Store annotation records for the posted comments.
     *
     * @param  Collection<int, Finding>  $findings
     * @param  array<string, mixed>  $reviewResponse
     */
    private function storeAnnotations(Run $run, Collection $findings, array $reviewResponse): void
    {
        $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
        $reviewId = $reviewResponse['id'] ?? null;
        $externalId = is_scalar($reviewId) ? (string) $reviewId : null;

        foreach ($findings as $finding) {
            Annotation::query()->create([
                'finding_id' => $finding->id,
                'workspace_id' => $run->workspace_id,
                'provider_id' => $provider?->id,
                'external_id' => $externalId,
                'type' => AnnotationType::Inline->value,
                'created_at' => now(),
            ]);
        }
    }
}
