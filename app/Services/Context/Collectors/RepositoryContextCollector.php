<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\GitHub\Support\GitHubContentDecoder;
use App\Services\GitHub\Support\RepositoryCoordinatesResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects repository context files like README and CONTRIBUTING.
 *
 * Fetches documentation files that help the AI understand project
 * conventions, coding standards, and contribution guidelines.
 */
final readonly class RepositoryContextCollector implements ContextCollector
{
    /**
     * Maximum content length for each file (in characters).
     * ~4000 tokens per file max.
     */
    private const int MAX_CONTENT_LENGTH = 16000;

    /**
     * Files to attempt to fetch in priority order.
     *
     * @var array<string>
     */
    private const array README_FILES = [
        'README.md',
        'readme.md',
        'README.MD',
        'README',
        'README.txt',
    ];

    /**
     * Contributing guide files in priority order.
     *
     * @var array<string>
     */
    private const array CONTRIBUTING_FILES = [
        'CONTRIBUTING.md',
        'contributing.md',
        '.github/CONTRIBUTING.md',
        'docs/CONTRIBUTING.md',
        'CONTRIBUTING',
    ];

    /**
     * Create a new RepositoryContextCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
        private GitHubContentDecoder $contentDecoder = new GitHubContentDecoder,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'repository_context';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 50; // Lower priority - supplementary context
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

        $coordinates = $this->coordinatesResolver->resolve($repository);

        if (! $coordinates instanceof \App\Services\GitHub\ValueObjects\RepositoryCoordinates) {
            return;
        }

        $context = [];
        $contextPaths = [];

        // Fetch README
        $readme = $this->fetchFirstAvailable(
            $coordinates->installationId,
            $coordinates->owner,
            $coordinates->repo,
            self::README_FILES
        );

        if ($readme !== null) {
            $context['readme'] = $this->truncateContent($readme['content'], 'README');
            $contextPaths['readme'] = $readme['path'];
        }

        // Fetch CONTRIBUTING guide
        $contributing = $this->fetchFirstAvailable(
            $coordinates->installationId,
            $coordinates->owner,
            $coordinates->repo,
            self::CONTRIBUTING_FILES
        );

        if ($contributing !== null) {
            $context['contributing'] = $this->truncateContent($contributing['content'], 'CONTRIBUTING');
            $contextPaths['contributing'] = $contributing['path'];
        }

        $bag->repositoryContext = $context;
        if ($contextPaths !== []) {
            $bag->metadata['repository_context_paths'] = $contextPaths;
        }

        Log::info('RepositoryContextCollector: Collected repository context', [
            'repository' => $coordinates->fullName,
            'has_readme' => isset($context['readme']),
            'has_contributing' => isset($context['contributing']),
        ]);
    }

    /**
     * Fetch the first available file from a list of candidates.
     *
     * @param  array<string>  $files
     * @return array{path: string, content: string}|null
     */
    private function fetchFirstAvailable(
        int $installationId,
        string $owner,
        string $repo,
        array $files
    ): ?array {
        foreach ($files as $file) {
            try {
                $content = $this->fetchFileContent($installationId, $owner, $repo, $file);
                if ($content !== null && $content !== '') {
                    return [
                        'path' => $file,
                        'content' => $content,
                    ];
                }
            } catch (Throwable) {
                // File doesn't exist, try next
                continue;
            }
        }

        return null;
    }

    /**
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(
        int $installationId,
        string $owner,
        string $repo,
        string $path
    ): ?string {
        try {
            $response = $this->gitHubApiService->getFileContents(
                $installationId,
                $owner,
                $repo,
                $path
            );
            $content = $this->contentDecoder->decode($response);

            if ($content !== null) {
                return $content;
            }

            Log::debug('RepositoryContextCollector: Unexpected response format', [
                'path' => $path,
            ]);

            return null;
        } catch (Throwable $throwable) {
            Log::debug('RepositoryContextCollector: Failed to fetch file', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Truncate content if it exceeds the maximum length.
     */
    private function truncateContent(string $content, string $type): string
    {
        if (mb_strlen($content) <= self::MAX_CONTENT_LENGTH) {
            return $content;
        }

        $truncated = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);

        // Try to break at a paragraph or line boundary
        $lastParagraph = mb_strrpos($truncated, "\n\n");
        $lastLine = mb_strrpos($truncated, "\n");

        if ($lastParagraph !== false && $lastParagraph > self::MAX_CONTENT_LENGTH * 0.8) {
            $truncated = mb_substr($truncated, 0, $lastParagraph);
        } elseif ($lastLine !== false && $lastLine > self::MAX_CONTENT_LENGTH * 0.9) {
            $truncated = mb_substr($truncated, 0, $lastLine);
        }

        return $truncated."\n\n[{$type} truncated due to length]";
    }
}
