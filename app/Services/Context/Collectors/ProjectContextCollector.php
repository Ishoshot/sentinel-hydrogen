<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\ProjectDependencyPrioritizer;
use App\Services\Context\Collectors\Support\ProjectManifestContextBuilder;
use App\Services\Context\Collectors\ProjectManifestFileFetcher;
use App\Services\Context\Collectors\Support\ProjectManifestParser;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;

/**
 * Collects project context including dependencies and versions.
 */
final readonly class ProjectContextCollector implements ContextCollector
{
    /**
     * Create a new ProjectContextCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private ProjectManifestParser $manifestParser = new ProjectManifestParser,
        private ProjectDependencyPrioritizer $dependencyPrioritizer = new ProjectDependencyPrioritizer,
        private ?ProjectManifestContextBuilder $contextBuilder = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'project_context';
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return 55;
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

        $repository->loadMissing('installation');

        $installation = $repository->installation;

        if ($installation === null) {
            return;
        }

        $fullName = $repository->full_name ?? '';

        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            return;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);
        $installationId = $installation->installation_id;

        $context = $this->contextBuilder()->build(
            installationId: $installationId,
            owner: $owner,
            repo: $repo,
            semantics: $bag->semantics,
        );

        if ($context['languages'] === [] && $context['dependencies'] === []) {
            return;
        }

        $bag->projectContext = $context;

        Log::info('ProjectContextCollector: Collected project context', [
            'repository' => $fullName,
            'languages' => $context['languages'],
            'frameworks_count' => count($context['frameworks']),
            'dependencies_count' => count($context['dependencies']),
        ]);
    }

    /**
     * ContextBuilder.
     */
    private function contextBuilder(): ProjectManifestContextBuilder
    {
        return $this->contextBuilder ?? new ProjectManifestContextBuilder(
            new ProjectManifestFileFetcher($this->gitHubApiService),
            $this->manifestParser,
            $this->dependencyPrioritizer,
        );
    }
}
