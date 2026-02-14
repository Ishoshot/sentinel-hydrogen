<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\LinkedIssueFetcher;
use App\Services\Context\Collectors\Support\LinkedIssueReferenceExtractor;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\RepositoryCoordinatesResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects linked issues from PR body references.
 *
 * Parses patterns like "Fixes #123", "Closes #456", "Resolves #789" from the PR body
 * and fetches the issue details including comments.
 */
final readonly class LinkedIssueCollector implements ContextCollector
{
    /**
     * Maximum number of issues to fetch to prevent API abuse.
     */
    private const int MAX_ISSUES = 5;

    /**
     * Create a new LinkedIssueCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private LinkedIssueReferenceExtractor $issueReferenceExtractor = new LinkedIssueReferenceExtractor,
        private LinkedIssueFetcher $issueFetcher = new LinkedIssueFetcher,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'linked_issues';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 80; // High priority - issue context is important
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        if (! isset($params['repository'], $params['run'])) {
            return false;
        }

        if (! $params['repository'] instanceof Repository || ! $params['run'] instanceof Run) {
            return false;
        }

        // Only collect if PR has a body that might contain issue references
        $metadata = $params['run']->metadata ?? [];
        $body = $metadata['pull_request_body'] ?? '';

        return is_string($body) && $body !== '';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(ContextBag $bag, array $params): void
    {
        /** @var Repository $repository */
        $repository = $params['repository'];

        /** @var Run $run */
        $run = $params['run'];

        $metadata = $run->metadata ?? [];
        $body = is_string($metadata['pull_request_body'] ?? null) ? $metadata['pull_request_body'] : '';

        $coordinates = $this->coordinatesResolver->resolve($repository);

        if (! $coordinates instanceof \App\Services\GitHub\ValueObjects\RepositoryCoordinates) {
            return;
        }

        // Extract issue numbers from PR body
        $issueNumbers = $this->extractIssueNumbers($body);

        if ($issueNumbers === []) {
            Log::debug('LinkedIssueCollector: No linked issues found in PR body');

            return;
        }

        // Limit the number of issues to fetch
        $issueNumbers = array_slice($issueNumbers, 0, self::MAX_ISSUES);

        $linkedIssues = [];

        foreach ($issueNumbers as $issueNumber) {
            try {
                $issue = $this->issueFetcher->fetchIssueWithComments(
                    $this->gitHubApiService,
                    $coordinates->installationId,
                    $coordinates->owner,
                    $coordinates->repo,
                    $issueNumber
                );

                if ($issue !== null) {
                    $linkedIssues[] = $issue;
                }
            } catch (Throwable $e) {
                Log::warning('LinkedIssueCollector: Failed to fetch issue', [
                    'issue_number' => $issueNumber,
                    'repository' => $coordinates->fullName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bag->linkedIssues = $linkedIssues;

        Log::info('LinkedIssueCollector: Collected linked issues', [
            'repository' => $coordinates->fullName,
            'issues_found' => count($issueNumbers),
            'issues_fetched' => count($linkedIssues),
        ]);
    }

    /**
     * Extract issue numbers from PR body text.
     *
     * @return array<int>
     */
    private function extractIssueNumbers(string $body): array
    {
        return $this->issueReferenceExtractor->extractIssueNumbers($body);
    }
}
