<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\AnnotationStyle;
use App\Enums\AnnotationType;
use App\Enums\ProviderType;
use App\Enums\SentinelConfigSeverity;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Provider;
use App\Models\Run;
use App\Services\GitHub\GitHubApiService;
use App\Services\SentinelMessageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Posts review annotations to GitHub as PR review comments.
 */
final readonly class PostRunAnnotations
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private GitHubApiService $gitHubApiService,
        private LogActivity $logActivity,
        private SentinelMessageService $messageService,
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

        $annotationsConfig = $this->getAnnotationsConfig($run);
        $eligibleFindings = $this->filterEligibleFindings($run, $annotationsConfig);

        if ($eligibleFindings->isEmpty()) {
            $this->postSummaryOnly($run, $installationId, $owner, $repo, $pullRequestNumber, $annotationsConfig);

            return 0;
        }

        $reviewBody = $this->buildReviewSummary($run);
        $inlineComments = $this->buildInlineComments($eligibleFindings, $annotationsConfig);

        // Get the commit SHA for line-based comments
        $commitId = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        /** @var array<string, mixed> $reviewResponse */
        $reviewResponse = $this->postAnnotations(
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
     * Get annotations configuration from the run's policy snapshot.
     *
     * @return array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}
     */
    private function getAnnotationsConfig(Run $run): array
    {
        $policy = $run->policy_snapshot ?? [];
        $annotations = is_array($policy['annotations'] ?? null) ? $policy['annotations'] : [];

        return [
            'style' => is_string($annotations['style'] ?? null) ? $annotations['style'] : 'review',
            'post_threshold' => is_string($annotations['post_threshold'] ?? null) ? $annotations['post_threshold'] : 'medium',
            'grouped' => (bool) ($annotations['grouped'] ?? true),
            'include_suggestions' => (bool) ($annotations['include_suggestions'] ?? true),
        ];
    }

    /**
     * Post annotations based on the configured style.
     *
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return array<string, mixed>
     */
    private function postAnnotations(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $reviewBody,
        array $inlineComments,
        ?string $commitId,
        array $config
    ): array {
        $style = AnnotationStyle::tryFrom($config['style']) ?? AnnotationStyle::Review;

        return match ($style) {
            AnnotationStyle::Review => $this->postAsReview(
                $installationId, $owner, $repo, $pullRequestNumber, $reviewBody, $inlineComments, $commitId, $config['grouped']
            ),
            AnnotationStyle::Comment => $this->postAsComments(
                $installationId, $owner, $repo, $pullRequestNumber, $reviewBody, $inlineComments, $config['grouped']
            ),
            AnnotationStyle::Check => $this->postAsCheckRun(
                $run, $installationId, $owner, $repo, $reviewBody, $inlineComments, $commitId
            ),
        };
    }

    /**
     * Post annotations as a GitHub PR review (default).
     *
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    private function postAsReview(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $reviewBody,
        array $inlineComments,
        ?string $commitId,
        bool $grouped
    ): array {
        if ($grouped) {
            /** @var array<string, mixed> $response */
            $response = $this->gitHubApiService->createPullRequestReview(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $reviewBody,
                'COMMENT',
                $inlineComments,
                $commitId
            );

            return $response;
        }

        // Post summary first, then individual review comments
        /** @var array<string, mixed> $response */
        $response = $this->gitHubApiService->createPullRequestReview(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $reviewBody,
            'COMMENT',
            [],
            $commitId
        );

        // Post individual comments as separate single-comment reviews
        foreach ($inlineComments as $comment) {
            $this->gitHubApiService->createPullRequestReview(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                '',
                'COMMENT',
                [$comment],
                $commitId
            );
        }

        return $response;
    }

    /**
     * Post annotations as individual PR comments (issue comments).
     *
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    private function postAsComments(
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        string $reviewBody,
        array $inlineComments,
        bool $grouped
    ): array {
        // Post summary as a PR comment
        $response = $this->gitHubApiService->createPullRequestComment(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber,
            $reviewBody
        );

        if ($grouped) {
            // Post all findings in a single comment
            $findingsBody = "## Detailed Findings\n\n";
            foreach ($inlineComments as $comment) {
                $findingsBody .= sprintf("### `%s` (line %d)\n\n%s\n\n---\n\n", $comment['path'], $comment['line'], $comment['body']);
            }

            $this->gitHubApiService->createPullRequestComment(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $findingsBody
            );
        } else {
            // Post each finding as a separate comment
            foreach ($inlineComments as $comment) {
                $commentBody = sprintf("**File:** `%s` (line %d)\n\n%s", $comment['path'], $comment['line'], $comment['body']);
                $this->gitHubApiService->createPullRequestComment(
                    $installationId,
                    $owner,
                    $repo,
                    $pullRequestNumber,
                    $commentBody
                );
            }
        }

        return $response;
    }

    /**
     * Post annotations as a GitHub Check Run.
     *
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $inlineComments
     * @return array<string, mixed>
     */
    private function postAsCheckRun(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        string $reviewBody,
        array $inlineComments,
        ?string $commitId
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
     * Post a summary-only review when there are no eligible findings.
     *
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     */
    private function postSummaryOnly(
        Run $run,
        int $installationId,
        string $owner,
        string $repo,
        int $pullRequestNumber,
        array $config
    ): void {
        $summary = $this->buildReviewSummary($run);
        $style = AnnotationStyle::tryFrom($config['style']) ?? AnnotationStyle::Review;
        $metadata = $run->metadata ?? [];
        $commitId = is_string($metadata['head_sha'] ?? null) ? $metadata['head_sha'] : null;

        match ($style) {
            AnnotationStyle::Review => $this->gitHubApiService->createPullRequestReview(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $summary,
                'COMMENT',
                []
            ),
            AnnotationStyle::Comment => $this->gitHubApiService->createPullRequestComment(
                $installationId,
                $owner,
                $repo,
                $pullRequestNumber,
                $summary
            ),
            AnnotationStyle::Check => $commitId !== null ? $this->gitHubApiService->createCheckRun(
                $installationId,
                $owner,
                $repo,
                'Sentinel Code Review',
                $commitId,
                'completed',
                'success',
                $summary,
                []
            ) : null,
        };
    }

    /**
     * Filter findings that are eligible for posting as comments.
     *
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return Collection<int, Finding>
     */
    private function filterEligibleFindings(Run $run, array $config): Collection
    {
        $policy = $run->policy_snapshot ?? [];
        $commentLimits = is_array($policy['comment_limits'] ?? null) ? $policy['comment_limits'] : [];

        // Use annotations.post_threshold for filtering which findings to post as annotations
        $severityThreshold = $config['post_threshold'];
        $maxComments = is_int($commentLimits['max_inline_comments'] ?? null) ? $commentLimits['max_inline_comments'] : 10;

        $minSeverityEnum = SentinelConfigSeverity::tryFrom($severityThreshold) ?? SentinelConfigSeverity::Medium;
        $minPriority = $minSeverityEnum->priority();

        return $run->findings
            ->filter(function (Finding $finding) use ($minPriority): bool {
                $findingPriority = $finding->severity?->priority() ?? 0;

                return $findingPriority >= $minPriority
                    && $finding->file_path !== null
                    && $finding->line_start !== null;
            })
            ->sortByDesc(fn (Finding $finding): int => $finding->severity?->priority() ?? 0)
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

        $runUrl = $this->buildRunUrl($run);

        return $body.$this->messageService->buildReviewSignOff($runUrl);
    }

    /**
     * Build the frontend URL for a run.
     */
    private function buildRunUrl(Run $run): string
    {
        /** @var string $frontendUrl */
        $frontendUrl = config('app.frontend_url');

        return sprintf(
            '%s/workspaces/%s/runs/%s',
            mb_rtrim($frontendUrl, '/'),
            $run->workspace?->slug,
            $run->id
        );
    }

    /**
     * Build inline comments for eligible findings.
     *
     * Uses GitHub's line-based comment format instead of position-based.
     * - `line`: The line number in the file (on the specified side)
     * - `side`: "RIGHT" for the new version of the file
     *
     * @param  Collection<int, Finding>  $findings
     * @param  array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}  $config
     * @return array<int, array{path: string, line: int, side: string, body: string}>
     */
    private function buildInlineComments(Collection $findings, array $config): array
    {
        $comments = [];
        $includeSuggestions = $config['include_suggestions'];

        foreach ($findings as $finding) {
            if ($finding->file_path === null) {
                continue;
            }

            if ($finding->line_start === null) {
                continue;
            }

            $comments[] = [
                'path' => $finding->file_path,
                'line' => $finding->line_start,
                'side' => 'RIGHT', // Comment on the new version of the file
                'body' => $this->formatFindingComment($finding, $includeSuggestions),
            ];
        }

        return $comments;
    }

    /**
     * Format a single finding as a comment body.
     */
    private function formatFindingComment(Finding $finding, bool $includeSuggestions = true): string
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $finding->metadata ?? [];

        $severityBadge = match ($finding->severity) {
            SentinelConfigSeverity::Critical => '**:red_circle: Critical**',
            SentinelConfigSeverity::High => '**:orange_circle: High**',
            SentinelConfigSeverity::Medium => '**:yellow_circle: Medium**',
            SentinelConfigSeverity::Low => '**:white_circle: Low**',
            default => '**:blue_circle: Info**',
        };

        $body = "{$severityBadge} | `{$finding->category->value}`\n\n";
        $body .= "### {$finding->title}\n\n";
        $body .= $finding->description.PHP_EOL;

        // Only include suggestions if configured
        if ($includeSuggestions) {
            $body .= $this->formatCodeSuggestion($metadata);
        }

        $impact = isset($metadata['impact']) && is_string($metadata['impact']) ? $metadata['impact'] : null;
        if ($impact !== null && $impact !== '') {
            $body .= "\n_Impact: {$impact}_\n";
        }

        $confidence = $finding->confidence;
        if ($confidence !== null) {
            $confidencePercent = (int) round($confidence * 100);
            $body .= "\n`Confidence: {$confidencePercent}%`";
        }

        return $body;
    }

    /**
     * Format code suggestion block for GitHub.
     *
     * Uses GitHub's suggestion syntax for one-click apply when replacement_code is available.
     * Falls back to text suggestion if only suggestion field is present.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function formatCodeSuggestion(array $metadata): string
    {
        $replacementCode = isset($metadata['replacement_code']) && is_string($metadata['replacement_code'])
            ? $metadata['replacement_code']
            : null;

        $explanation = isset($metadata['explanation']) && is_string($metadata['explanation'])
            ? $metadata['explanation']
            : null;

        // If we have replacement code, use GitHub's suggestion block
        if ($replacementCode !== null && $replacementCode !== '') {
            $body = "\n";

            if ($explanation !== null && $explanation !== '') {
                $body .= "**Why:** {$explanation}\n\n";
            }

            // GitHub suggestion block - allows one-click apply
            $body .= "```suggestion\n";
            $body .= $replacementCode;
            // Ensure the suggestion ends with a newline
            if (! str_ends_with($replacementCode, "\n")) {
                $body .= "\n";
            }

            return $body."```\n";
        }

        // Fallback to text suggestion if no code replacement
        $suggestion = isset($metadata['suggestion']) && is_string($metadata['suggestion'])
            ? $metadata['suggestion']
            : null;

        if ($suggestion !== null && $suggestion !== '') {
            return "\n**Suggestion:** {$suggestion}\n";
        }

        return '';
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
