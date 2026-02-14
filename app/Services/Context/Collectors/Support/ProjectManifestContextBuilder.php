<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

/**
 * Builds normalized project context from repository manifest files.
 */
final readonly class ProjectManifestContextBuilder
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
     * Create a new context builder instance.
     */
    public function __construct(
        private ProjectManifestFileFetcher $manifestFileFetcher,
        private ProjectManifestParser $manifestParser = new ProjectManifestParser,
        private ProjectDependencyPrioritizer $dependencyPrioritizer = new ProjectDependencyPrioritizer,
    ) {}

    /**
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array{
     *   languages: array<int, string>,
     *   runtime: array{name: string, version: string}|null,
     *   frameworks: array<int, array{name: string, version: string}>,
     *   dependencies: array<int, array{name: string, version: string, dev?: bool}>
     * }
     */
    public function build(int $installationId, string $owner, string $repo, array $semantics): array
    {
        $context = [
            'languages' => [],
            'runtime' => null,
            'frameworks' => [],
            'dependencies' => [],
        ];

        foreach (self::MANIFEST_FILES as $language => $manifestFiles) {
            $parsedManifest = $this->parseFirstAvailableManifest($installationId, $owner, $repo, $manifestFiles);

            if ($parsedManifest === null) {
                continue;
            }

            $context['languages'][] = $language;

            if (isset($parsedManifest['runtime'])) {
                $context['runtime'] = $parsedManifest['runtime'];
            }

            if (! empty($parsedManifest['frameworks'])) {
                $context['frameworks'] = array_merge($context['frameworks'], $parsedManifest['frameworks']);
            }

            if (! empty($parsedManifest['dependencies'])) {
                $context['dependencies'] = array_merge($context['dependencies'], $parsedManifest['dependencies']);
            }
        }

        $importedModules = $this->dependencyPrioritizer->extractImportedModules($semantics);

        $context['languages'] = array_values(array_unique($context['languages']));
        $context['frameworks'] = $this->dependencyPrioritizer->deduplicateByName($context['frameworks']);
        $context['dependencies'] = $this->dependencyPrioritizer->limitDependencies($context['dependencies'], $importedModules);

        return $context;
    }

    /**
     * @param  array<string>  $manifestFiles
     * @return array{
     *   runtime?: array{name: string, version: string},
     *   frameworks?: array<int, array{name: string, version: string}>,
     *   dependencies?: array<int, array{name: string, version: string, dev?: bool}>
     * }|null
     */
    private function parseFirstAvailableManifest(
        int $installationId,
        string $owner,
        string $repo,
        array $manifestFiles
    ): ?array {
        foreach ($manifestFiles as $manifestFile) {
            if (str_contains($manifestFile, '*')) {
                continue;
            }

            $content = $this->manifestFileFetcher->fetch($installationId, $owner, $repo, $manifestFile);

            if ($content === null) {
                continue;
            }

            $parsed = $this->manifestParser->parseManifest($manifestFile, $content);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }
}
