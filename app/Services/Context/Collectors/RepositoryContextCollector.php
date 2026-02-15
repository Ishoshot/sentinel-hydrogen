<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\RepositoryDocumentFetcher;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Resolvers\RepositoryCoordinatesResolver;
use Illuminate\Support\Facades\Log;

/**
 * Collects repository context files like README and CONTRIBUTING.
 *
 * Fetches documentation files that help the AI understand project
 * conventions, coding standards, and contribution guidelines.
 */
final readonly class RepositoryContextCollector implements ContextCollector
{
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
        private RepositoryDocumentFetcher $documentFetcher,
        private RepositoryCoordinatesResolver $coordinatesResolver = new RepositoryCoordinatesResolver,
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

        $readme = $this->documentFetcher->fetchFirstAvailable(
            $coordinates->installationId,
            $coordinates->owner,
            $coordinates->repo,
            self::README_FILES
        );

        if ($readme !== null) {
            $context['readme'] = $this->documentFetcher->truncateContent($readme['content'], 'README');
            $contextPaths['readme'] = $readme['path'];
        }

        $contributing = $this->documentFetcher->fetchFirstAvailable(
            $coordinates->installationId,
            $coordinates->owner,
            $coordinates->repo,
            self::CONTRIBUTING_FILES
        );

        if ($contributing !== null) {
            $context['contributing'] = $this->documentFetcher->truncateContent($contributing['content'], 'CONTRIBUTING');
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
}
