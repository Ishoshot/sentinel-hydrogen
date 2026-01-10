<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\GitHubApiService;
use Illuminate\Support\Facades\Log;

/**
 * Collects PR metadata and file diffs from GitHub.
 *
 * This is the highest priority collector as code changes are essential for reviews.
 */
final readonly class DiffCollector implements ContextCollector
{
    /**
     * Create a new DiffCollector instance.
     */
    public function __construct(private GitHubApiService $gitHubApiService) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'diff';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 100; // Highest priority - code diffs are essential
    }

    /**
     * {@inheritdoc}
     */
    public function shouldCollect(array $params): bool
    {
        return isset($params['repository'], $params['run'])
            && $params['repository'] instanceof Repository
            && $params['run'] instanceof Run;
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
        $repository->loadMissing('installation');
        $installation = $repository->installation;

        if ($installation === null) {
            Log::warning('DiffCollector: Repository has no installation', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            Log::warning('DiffCollector: Invalid repository full name', [
                'repository_id' => $repository->id,
            ]);

            return;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;
        $pullRequestNumber = is_int($metadata['pull_request_number'] ?? null)
            ? $metadata['pull_request_number']
            : 0;

        if ($pullRequestNumber <= 0) {
            Log::warning('DiffCollector: Invalid PR number', ['run_id' => $run->id]);

            return;
        }

        // Populate PR metadata from run metadata
        $bag->pullRequest = $this->extractPullRequestData($metadata, $fullName);

        // Fetch files with patches from GitHub
        $files = $this->gitHubApiService->getPullRequestFiles(
            $installationId,
            $owner,
            $repo,
            $pullRequestNumber
        );

        // @phpstan-ignore function.alreadyNarrowedType (defensive check against GitHub API changes)
        if (! is_array($files)) {
            Log::warning('DiffCollector: Unexpected response format from GitHub API', [
                'pr_number' => $pullRequestNumber,
            ]);

            return;
        }

        $bag->files = $this->normalizeFiles($files);
        $bag->metrics = $this->calculateMetrics($bag->files);

        Log::info('DiffCollector: Collected PR data', [
            'repository' => $fullName,
            'pr_number' => $pullRequestNumber,
            'files_count' => count($bag->files),
            'files_with_patches' => $bag->getFilesWithPatchCount(),
        ]);
    }

    /**
     * Extract PR metadata from run metadata.
     *
     * @param  array<string, mixed>  $metadata
     * @return array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string, author: array{login: string, avatar_url: string|null}, is_draft: bool, assignees: array<int, array{login: string, avatar_url: string|null}>, reviewers: array<int, array{login: string, avatar_url: string|null}>, labels: array<int, array{name: string, color: string}>}
     */
    private function extractPullRequestData(array $metadata, string $fullName): array
    {
        return [
            'number' => $this->getInt($metadata, 'pull_request_number', 0),
            'title' => $this->getString($metadata, 'pull_request_title', ''),
            'body' => $this->getStringOrNull($metadata, 'pull_request_body'),
            'base_branch' => $this->getString($metadata, 'base_branch', 'main'),
            'head_branch' => $this->getString($metadata, 'head_branch', ''),
            'head_sha' => $this->getString($metadata, 'head_sha', ''),
            'sender_login' => $this->getString($metadata, 'sender_login', ''),
            'repository_full_name' => $fullName,
            'author' => $this->getAuthor($metadata),
            'is_draft' => $this->getBool($metadata, 'is_draft', false),
            'assignees' => $this->getUsers($metadata, 'assignees'),
            'reviewers' => $this->getUsers($metadata, 'reviewers'),
            'labels' => $this->getLabels($metadata),
        ];
    }

    /**
     * Normalize GitHub file data to include patches.
     *
     * @param  array<int, array<string, mixed>>  $files
     * @return array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>
     */
    private function normalizeFiles(array $files): array
    {
        return array_map(fn (array $file): array => [
            'filename' => $this->getString($file, 'filename', ''),
            'status' => $this->getString($file, 'status', 'modified'),
            'additions' => $this->getInt($file, 'additions', 0),
            'deletions' => $this->getInt($file, 'deletions', 0),
            'changes' => $this->getInt($file, 'changes', 0),
            'patch' => $this->getStringOrNull($file, 'patch'),
        ], $files);
    }

    /**
     * Calculate metrics from normalized files.
     *
     * @param  array<int, array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}>  $files
     * @return array{files_changed: int, lines_added: int, lines_deleted: int}
     */
    private function calculateMetrics(array $files): array
    {
        return [
            'files_changed' => count($files),
            'lines_added' => array_sum(array_column($files, 'additions')),
            'lines_deleted' => array_sum(array_column($files, 'deletions')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getString(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getStringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getInt(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getBool(array $data, string $key, bool $default): bool
    {
        $value = $data[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{login: string, avatar_url: string|null}
     */
    private function getAuthor(array $metadata): array
    {
        $author = $metadata['author'] ?? null;
        $fallbackLogin = $this->getString($metadata, 'sender_login', '');

        if (is_array($author) && isset($author['login']) && is_string($author['login'])) {
            return [
                'login' => $author['login'],
                'avatar_url' => isset($author['avatar_url']) && is_string($author['avatar_url'])
                    ? $author['avatar_url']
                    : null,
            ];
        }

        return [
            'login' => $fallbackLogin,
            'avatar_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{login: string, avatar_url: string|null}>
     */
    private function getUsers(array $metadata, string $key): array
    {
        $users = $metadata[$key] ?? [];

        if (! is_array($users)) {
            return [];
        }

        $result = [];
        foreach ($users as $user) {
            if (is_array($user) && isset($user['login']) && is_string($user['login'])) {
                $result[] = [
                    'login' => $user['login'],
                    'avatar_url' => isset($user['avatar_url']) && is_string($user['avatar_url'])
                        ? $user['avatar_url']
                        : null,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{name: string, color: string}>
     */
    private function getLabels(array $metadata): array
    {
        $labels = $metadata['labels'] ?? [];

        if (! is_array($labels)) {
            return [];
        }

        $result = [];
        foreach ($labels as $label) {
            if (is_array($label) && isset($label['name']) && is_string($label['name'])) {
                $result[] = [
                    'name' => $label['name'],
                    'color' => isset($label['color']) && is_string($label['color'])
                        ? $label['color']
                        : 'cccccc',
                ];
            }
        }

        return $result;
    }
}
