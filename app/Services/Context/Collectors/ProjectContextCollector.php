<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\Collectors\Support\ProjectDependencyPrioritizer;
use App\Services\Context\Collectors\Support\ProjectManifestParser;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects project context including dependencies and versions.
 */
final readonly class ProjectContextCollector implements ContextCollector
{
    /**
     * Package manifest files in priority order per ecosystem.
     *
     * @var array<string, array<string>>
     */
    private const array MANIFEST_FILES = [
        'php' => ['composer.json'],
        'javascript' => ['package.json'],
        'python' => ['pyproject.toml', 'requirements.txt', 'setup.py', 'Pipfile'],
        'go' => ['go.mod'],
        'rust' => ['Cargo.toml'],
        'ruby' => ['Gemfile', 'Gemfile.lock'],
        'java' => ['pom.xml', 'build.gradle', 'build.gradle.kts'],
        'dotnet' => ['*.csproj', '*.fsproj', 'packages.config'],
        'swift' => ['Package.swift'],
        'dart' => ['pubspec.yaml'],
        'elixir' => ['mix.exs'],
    ];

    /**
     * Create a new ProjectContextCollector instance.
     */
    public function __construct(
        private GitHubApiServiceContract $gitHubApiService,
        private ProjectManifestParser $manifestParser = new ProjectManifestParser,
        private ProjectDependencyPrioritizer $dependencyPrioritizer = new ProjectDependencyPrioritizer,
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

        $context = [
            'languages' => [],
            'runtime' => null,
            'frameworks' => [],
            'dependencies' => [],
        ];

        foreach (self::MANIFEST_FILES as $language => $manifestFiles) {
            foreach ($manifestFiles as $manifestFile) {
                if (str_contains($manifestFile, '*')) {
                    continue;
                }

                $content = $this->fetchFileContent($installationId, $owner, $repo, $manifestFile);

                if ($content === null) {
                    continue;
                }

                $parsed = $this->manifestParser->parseManifest($manifestFile, $content);

                if ($parsed === null) {
                    continue;
                }

                $context['languages'][] = $language;

                if (isset($parsed['runtime'])) {
                    $context['runtime'] = $parsed['runtime'];
                }

                if (! empty($parsed['frameworks'])) {
                    $context['frameworks'] = array_merge($context['frameworks'], $parsed['frameworks']);
                }

                if (! empty($parsed['dependencies'])) {
                    $context['dependencies'] = array_merge($context['dependencies'], $parsed['dependencies']);
                }

                break;
            }
        }

        $importedModules = $this->dependencyPrioritizer->extractImportedModules($bag->semantics);

        $context['languages'] = array_values(array_unique($context['languages']));
        $context['frameworks'] = $this->dependencyPrioritizer->deduplicateByName($context['frameworks']);
        $context['dependencies'] = $this->dependencyPrioritizer->limitDependencies($context['dependencies'], $importedModules);

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
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(int $installationId, string $owner, string $repo, string $path): ?string
    {
        try {
            $response = $this->gitHubApiService->getFileContents($installationId, $owner, $repo, $path);

            if (is_string($response)) {
                return $response;
            }

            if (! isset($response['content']) || ! is_string($response['content'])) {
                return null;
            }

            $content = $response['content'];
            $encoding = $response['encoding'] ?? 'base64';

            if ($encoding !== 'base64') {
                return $content;
            }

            $decoded = base64_decode(str_replace("\n", '', $content), true);

            return $decoded !== false ? $decoded : null;
        } catch (Throwable $throwable) {
            Log::debug('ProjectContextCollector: Failed to fetch file', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
