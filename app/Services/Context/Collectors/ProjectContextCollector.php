<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors;

use App\Models\Repository;
use App\Models\Run;
use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects project context including dependencies and versions.
 *
 * Parses package manifest files (composer.json, package.json, etc.)
 * to understand the project's technology stack and dependency versions.
 * This helps the AI provide version-specific guidance.
 */
final readonly class ProjectContextCollector implements ContextCollector
{
    /**
     * Maximum number of dependencies to include per category.
     * We prioritize main dependencies over dev dependencies.
     */
    private const int MAX_MAIN_DEPENDENCIES = 50;

    private const int MAX_DEV_DEPENDENCIES = 20;

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
     * Known frameworks for categorization.
     *
     * @var array<string, array<string, string>>
     */
    private const array KNOWN_FRAMEWORKS = [
        'php' => [
            'laravel/framework' => 'Laravel',
            'symfony/symfony' => 'Symfony',
            'slim/slim' => 'Slim',
            'cakephp/cakephp' => 'CakePHP',
            'yiisoft/yii2' => 'Yii',
        ],
        'javascript' => [
            'react' => 'React',
            'vue' => 'Vue.js',
            'next' => 'Next.js',
            'nuxt' => 'Nuxt',
            '@angular/core' => 'Angular',
            'svelte' => 'Svelte',
            'express' => 'Express',
            'fastify' => 'Fastify',
            '@nestjs/core' => 'NestJS',
        ],
        'python' => [
            'django' => 'Django',
            'flask' => 'Flask',
            'fastapi' => 'FastAPI',
            'tornado' => 'Tornado',
        ],
        'ruby' => [
            'rails' => 'Ruby on Rails',
            'sinatra' => 'Sinatra',
            'hanami' => 'Hanami',
        ],
        'rust' => [
            'actix-web' => 'Actix Web',
            'rocket' => 'Rocket',
            'axum' => 'Axum',
            'warp' => 'Warp',
        ],
        'go' => [
            'github.com/gin-gonic/gin' => 'Gin',
            'github.com/labstack/echo' => 'Echo',
            'github.com/gofiber/fiber' => 'Fiber',
        ],
    ];

    /**
     * Rust standard library modules to filter out.
     *
     * @var array<int, string>
     */
    private const array RUST_STD_MODULES = ['std', 'core', 'alloc', 'self', 'super', 'crate'];

    /**
     * Standard library roots to filter out when normalizing imports.
     *
     * @var array<int, string>
     */
    private const array STD_LIB_ROOTS = [
        // Java
        'java', 'javax', 'sun', 'com.sun',
        // C#/.NET
        'System', 'Microsoft',
        // Python (built-ins)
        'os', 'sys', 'io', 're', 'json', 'typing', 'collections', 'functools', 'itertools',
        // Elixir (built-ins)
        'Kernel', 'Enum', 'List', 'Map', 'String', 'IO', 'File',
    ];

    /**
     * Create a new ProjectContextCollector instance.
     */
    public function __construct(private GitHubApiServiceContract $gitHubApiService) {}

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
        return 55; // Between repository context (50) and review history (60)
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

        // Try each ecosystem's manifest files
        foreach (self::MANIFEST_FILES as $language => $manifestFiles) {
            foreach ($manifestFiles as $manifestFile) {
                // Handle glob patterns like *.csproj
                if (str_contains($manifestFile, '*')) {
                    continue; // Skip glob patterns for now - would need directory listing
                }

                $content = $this->fetchFileContent($installationId, $owner, $repo, $manifestFile);

                if ($content === null) {
                    continue;
                }

                $parsed = $this->parseManifest($manifestFile, $content);

                if ($parsed !== null) {
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

                    // Found a manifest for this language, move to next language
                    break;
                }
            }
        }

        // Extract imported modules from semantic analysis for usage-based filtering
        $importedModules = $this->extractImportedModules($bag->semantics);

        // Deduplicate and limit, prioritizing dependencies that are actually used
        $context['languages'] = array_values(array_unique($context['languages']));
        $context['frameworks'] = $this->deduplicateByName($context['frameworks']);
        $context['dependencies'] = $this->limitDependencies($context['dependencies'], $importedModules);

        // Only set if we found something
        if ($context['languages'] !== [] || $context['dependencies'] !== []) {
            $bag->projectContext = $context;

            Log::info('ProjectContextCollector: Collected project context', [
                'repository' => $fullName,
                'languages' => $context['languages'],
                'frameworks_count' => count($context['frameworks']),
                'dependencies_count' => count($context['dependencies']),
            ]);
        }
    }

    /**
     * Parse a manifest file based on its type.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseManifest(string $filename, string $content): ?array
    {
        return match ($filename) {
            'composer.json' => $this->parseComposerJson($content),
            'package.json' => $this->parsePackageJson($content),
            'go.mod' => $this->parseGoMod($content),
            'Cargo.toml' => $this->parseCargoToml($content),
            'pyproject.toml' => $this->parsePyprojectToml($content),
            'requirements.txt' => $this->parseRequirementsTxt($content),
            'Gemfile' => $this->parseGemfile($content),
            'pubspec.yaml' => $this->parsePubspecYaml($content),
            'mix.exs' => $this->parseMixExs($content),
            'pom.xml' => $this->parsePomXml($content),
            'build.gradle', 'build.gradle.kts' => $this->parseGradleBuild($content),
            default => null,
        };
    }

    /**
     * Parse PHP composer.json.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseComposerJson(string $content): ?array
    {
        /** @var array{require?: array<string, string>, require-dev?: array<string, string>}|null $json */
        $json = json_decode($content, true);

        if (! is_array($json)) {
            return null;
        }

        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract PHP version requirement
        $require = $json['require'] ?? [];
        if (isset($require['php'])) {
            $result['runtime'] = ['name' => 'PHP', 'version' => $require['php']];
        }

        // Extract main dependencies
        foreach ($require as $package => $version) {
            // Skip PHP version and extensions
            if ($package === 'php') {
                continue;
            }

            if (str_starts_with($package, 'ext-')) {
                continue;
            }

            $this->addDependencyWithFrameworkDetection($result, ['name' => $package, 'version' => $version], 'php');
        }

        // Extract dev dependencies
        foreach ($json['require-dev'] ?? [] as $package => $version) {
            $this->addDependencyWithFrameworkDetection($result, ['name' => $package, 'version' => $version], 'php', isDev: true);
        }

        return $result;
    }

    /**
     * Parse JavaScript/TypeScript package.json.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parsePackageJson(string $content): ?array
    {
        /** @var array{engines?: array{node?: string}, dependencies?: array<string, string>, devDependencies?: array<string, string>}|null $json */
        $json = json_decode($content, true);

        if (! is_array($json)) {
            return null;
        }

        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract Node version requirement
        if (isset($json['engines']['node'])) {
            $result['runtime'] = ['name' => 'Node.js', 'version' => $json['engines']['node']];
        }

        // Extract main dependencies
        foreach ($json['dependencies'] ?? [] as $package => $version) {
            $this->addDependencyWithFrameworkDetection($result, ['name' => $package, 'version' => $version], 'javascript');
        }

        // Extract dev dependencies
        foreach ($json['devDependencies'] ?? [] as $package => $version) {
            $this->addDependencyWithFrameworkDetection($result, ['name' => $package, 'version' => $version], 'javascript', isDev: true);
        }

        return $result;
    }

    /**
     * Parse Go go.mod file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseGoMod(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract Go version
        if (preg_match('/^go\s+(\d+\.\d+(?:\.\d+)?)/m', $content, $matches)) {
            $result['runtime'] = ['name' => 'Go', 'version' => $matches[1]];
        }

        // Track seen packages to avoid duplicates
        $seen = [];

        // Extract single-line requires
        if (preg_match_all('/^\s*require\s+([^\s]+)\s+([^\s]+)/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $seen[$match[1]] = true;
                $this->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'go');
            }
        }

        // Also parse require block
        if (preg_match('/require\s*\(\s*(.*?)\s*\)/s', $content, $blockMatch) && preg_match_all('/^\s*([^\s]+)\s+([^\s]+)/m', $blockMatch[1], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($seen[$match[1]])) {
                    continue;
                }

                $this->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'go');
            }
        }

        return $result;
    }

    /**
     * Parse Rust Cargo.toml file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseCargoToml(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract rust-version if specified
        if (preg_match('/^rust-version\s*=\s*"([^"]+)"/m', $content, $matches)) {
            $result['runtime'] = ['name' => 'Rust', 'version' => $matches[1]];
        }

        // Extract [dependencies] section (stop at next section marker on its own line)
        if (preg_match('/^\[dependencies\]\s*$(.*?)(?=^\[|\z)/ms', $content, $depsMatch)) {
            $this->parseCargoSection($depsMatch[1], $result, false);
        }

        // Extract [dev-dependencies] section
        if (preg_match('/^\[dev-dependencies\]\s*$(.*?)(?=^\[|\z)/ms', $content, $devMatch)) {
            $this->parseCargoSection($devMatch[1], $result, true);
        }

        return $result;
    }

    /**
     * Parse a Cargo.toml dependency section.
     *
     * @param  array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}  $result
     */
    private function parseCargoSection(string $section, array &$result, bool $isDev): void
    {
        // Simple format: package = "version"
        if (preg_match_all('/^([a-zA-Z0-9_-]+)\s*=\s*"([^"]+)"/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'rust', $isDev);
            }
        }

        // Complex format: package = { version = "..." }
        if (preg_match_all('/^([a-zA-Z0-9_-]+)\s*=\s*\{[^}]*version\s*=\s*"([^"]+)"/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'rust', $isDev);
            }
        }
    }

    /**
     * Parse Python pyproject.toml file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parsePyprojectToml(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract Python version requirement
        if (preg_match('/requires-python\s*=\s*"([^"]+)"/i', $content, $matches)) {
            $result['runtime'] = ['name' => 'Python', 'version' => $matches[1]];
        }

        // Extract dependencies array
        if (preg_match('/dependencies\s*=\s*\[(.*?)\]/s', $content, $depsMatch) && preg_match_all('/"([^"]+)"/s', $depsMatch[1], $packages)) {
            foreach ($packages[1] as $package) {
                $parsed = $this->parsePythonDependency($package);
                if ($parsed !== null) {
                    $this->addDependencyWithFrameworkDetection($result, $parsed, 'python');
                }
            }
        }

        return $result;
    }

    /**
     * Parse Python requirements.txt file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseRequirementsTxt(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        foreach (explode("\n", $content) as $line) {
            $line = mb_trim($line);
            // Skip empty lines, comments, and flags
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, '-')) {
                continue;
            }

            $parsed = $this->parsePythonDependency($line);
            if ($parsed === null) {
                continue;
            }

            $this->addDependencyWithFrameworkDetection($result, $parsed, 'python');
        }

        return $result;
    }

    /**
     * Parse a single Python dependency string.
     *
     * @return array{name: string, version: string}|null
     */
    private function parsePythonDependency(string $dependency): ?array
    {
        // Match patterns like: package==1.0.0, package>=1.0, package~=1.0
        if (preg_match('/^([a-zA-Z0-9_-]+)(?:\[.*?\])?([<>=!~]+)(.+)$/', $dependency, $matches)) {
            return ['name' => $matches[1], 'version' => $matches[2].$matches[3]];
        }

        // Just package name without version
        if (preg_match('/^([a-zA-Z0-9_-]+)(?:\[.*?\])?$/', $dependency, $matches)) {
            return ['name' => $matches[1], 'version' => '*'];
        }

        return null;
    }

    /**
     * Parse Ruby Gemfile.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseGemfile(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract ruby version
        if (preg_match('/ruby\s+["\']([^"\']+)["\']/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Ruby', 'version' => $matches[1]];
        }

        // Extract gems: gem 'name', '~> version' or gem 'name'
        if (preg_match_all('/gem\s+["\']([^"\']+)["\'](?:,\s*["\']([^"\']+)["\'])?/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2] ?? '*'], 'ruby');
            }
        }

        return $result;
    }

    /**
     * Parse Dart/Flutter pubspec.yaml.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parsePubspecYaml(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract SDK version
        if (preg_match('/sdk:\s*["\']?([^"\'\n]+)/i', $content, $matches)) {
            $result['runtime'] = ['name' => 'Dart', 'version' => mb_trim($matches[1])];
        }

        // Check for Flutter
        if (str_contains($content, 'flutter:')) {
            $result['frameworks'][] = ['name' => 'Flutter', 'version' => '*'];
        }

        // Simple YAML parsing for dependencies
        if (preg_match('/^dependencies:\s*\n((?:\s+[^\n]+\n?)*)/m', $content, $depsMatch)) {
            $this->parsePubspecDependencies($depsMatch[1], $result, false);
        }

        if (preg_match('/^dev_dependencies:\s*\n((?:\s+[^\n]+\n?)*)/m', $content, $devMatch)) {
            $this->parsePubspecDependencies($devMatch[1], $result, true);
        }

        return $result;
    }

    /**
     * Parse pubspec.yaml dependencies section.
     *
     * @param  array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}  $result
     */
    private function parsePubspecDependencies(string $section, array &$result, bool $isDev): void
    {
        // Match: package_name: ^1.0.0 or package_name: any
        if (preg_match_all('/^\s{2}([a-z_]+):\s*(?:\^|>=?|<)?([0-9.]+|\*|any)?/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2] ?? '*'], 'dart', $isDev);
            }
        }
    }

    /**
     * Parse Elixir mix.exs file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseMixExs(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract Elixir version
        if (preg_match('/elixir:\s*"([^"]+)"/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Elixir', 'version' => $matches[1]];
        }

        // Check for Phoenix
        if (str_contains($content, ':phoenix')) {
            if (preg_match('/:phoenix,\s*"([^"]+)"/', $content, $matches)) {
                $result['frameworks'][] = ['name' => 'Phoenix', 'version' => $matches[1]];
            } else {
                $result['frameworks'][] = ['name' => 'Phoenix', 'version' => '*'];
            }
        }

        // Extract deps
        if (preg_match_all('/\{:([a-z_]+),\s*"([^"]+)"/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result['dependencies'][] = ['name' => $match[1], 'version' => $match[2]];
            }
        }

        return $result;
    }

    /**
     * Parse Java Maven pom.xml file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parsePomXml(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract Java version
        if (preg_match('/<java\.version>([^<]+)</', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        } elseif (preg_match('/<maven\.compiler\.source>([^<]+)</', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        }

        // Check for Spring Boot
        if (str_contains($content, 'spring-boot')) {
            if (preg_match('/<spring-boot\.version>([^<]+)</', $content, $matches)) {
                $result['frameworks'][] = ['name' => 'Spring Boot', 'version' => $matches[1]];
            } else {
                $result['frameworks'][] = ['name' => 'Spring Boot', 'version' => '*'];
            }
        }

        // Extract dependencies
        if (preg_match_all('/<dependency>.*?<groupId>([^<]+)<.*?<artifactId>([^<]+)<.*?(?:<version>([^<]+)<)?.*?<\/dependency>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $package = $match[1].'/'.$match[2];
                $version = $match[3] ?? '*';

                $result['dependencies'][] = ['name' => $package, 'version' => $version];
            }
        }

        return $result;
    }

    /**
     * Parse Java/Kotlin Gradle build file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseGradleBuild(string $content): array
    {
        $result = [
            'frameworks' => [],
            'dependencies' => [],
        ];

        // Extract Java version
        if (preg_match('/sourceCompatibility\s*[=:]\s*[\'"]?(\d+)[\'"]?/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        } elseif (preg_match('/JavaLanguageVersion\.of\((\d+)\)/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        }

        // Check for Spring Boot
        if (str_contains($content, 'spring-boot')) {
            $result['frameworks'][] = ['name' => 'Spring Boot', 'version' => '*'];
        }

        // Extract dependencies
        if (preg_match_all('/(?:implementation|api|compile|testImplementation)\s*[(\s][\'"]([^:]+):([^:]+):([^\'"]+)[\'"]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $package = $match[1].'/'.$match[2];
                $version = $match[3];

                $result['dependencies'][] = ['name' => $package, 'version' => $version];
            }
        }

        return $result;
    }

    /**
     * Fetch file content from GitHub.
     */
    private function fetchFileContent(int $installationId, string $owner, string $repo, string $path): ?string
    {
        try {
            $response = $this->gitHubApiService->getFileContents(
                $installationId,
                $owner,
                $repo,
                $path
            );

            if (is_string($response)) {
                return $response;
            }

            // @phpstan-ignore function.alreadyNarrowedType (defensive check)
            if (! is_array($response)) {
                return null;
            }

            if (isset($response['content']) && is_string($response['content'])) {
                $content = $response['content'];
                $encoding = $response['encoding'] ?? 'base64';

                if ($encoding === 'base64') {
                    $decoded = base64_decode(str_replace("\n", '', $content), true);

                    return $decoded !== false ? $decoded : null;
                }

                return $content;
            }

            return null;
        } catch (Throwable $throwable) {
            Log::debug('ProjectContextCollector: Failed to fetch file', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Add a dependency and detect if it's a known framework.
     *
     * @param  array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}  $result
     * @param  array{name: string, version: string, dev?: bool}  $dependency
     */
    private function addDependencyWithFrameworkDetection(array &$result, array $dependency, string $language, bool $isDev = false): void
    {
        if ($isDev) {
            $dependency['dev'] = true;
        }

        if (isset(self::KNOWN_FRAMEWORKS[$language][$dependency['name']])) {
            $result['frameworks'][] = [
                'name' => self::KNOWN_FRAMEWORKS[$language][$dependency['name']],
                'version' => $dependency['version'],
            ];
        }

        $result['dependencies'][] = $dependency;
    }

    /**
     * Deduplicate an array of items by name.
     *
     * @param  array<int, array{name: string, version: string}>  $items
     * @return array<int, array{name: string, version: string}>
     */
    private function deduplicateByName(array $items): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            if (! isset($seen[$item['name']])) {
                $seen[$item['name']] = true;
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Extract imported module names from semantic analysis data.
     *
     * @param  array<string, array<string, mixed>>  $semantics
     * @return array<string>
     */
    private function extractImportedModules(array $semantics): array
    {
        $modules = [];

        foreach ($semantics as $data) {
            $imports = $data['imports'] ?? [];

            if (! is_array($imports)) {
                continue;
            }

            foreach ($imports as $import) {
                if (isset($import['module']) && is_string($import['module'])) {
                    $module = $import['module'];

                    // Normalize module names for matching
                    // PHP: "App\Services\Example" -> ignore internal, keep packages like "Illuminate\Support\Facades\Log"
                    // JS: "@angular/core" stays as-is, "react" stays as-is
                    // Python: "django.http" -> "django"
                    // Go: "github.com/gin-gonic/gin" stays as-is
                    $normalized = $this->normalizeModuleName($module);

                    if ($normalized !== null) {
                        $modules[] = $normalized;
                    }
                }
            }
        }

        return array_unique($modules);
    }

    /**
     * Normalize a module name to match against dependencies.
     *
     * Handles import patterns from all 33+ supported languages:
     * - PHP: Backslash namespaces (Illuminate\Support\Facades\Log)
     * - Python/Java/C#/Scala/Kotlin/Elixir/Haskell/OCaml/Julia/Lua: Dot notation (django.http)
     * - Rust/Perl/R: Double colon (tokio::sync::mpsc, dplyr::filter)
     * - Go: Full URL paths (github.com/gin-gonic/gin)
     * - JavaScript/TypeScript: Simple names or scoped (@angular/core)
     * - Ruby/Swift/Objective-C: Simple names
     * - Clojure: Slash for qualified symbols (clojure.core/map)
     * - Dart: package: prefix (package:flutter/material.dart)
     * - C/C++: Header includes (usually not useful for dependency matching)
     *
     * Returns null if the module is internal (not from a package).
     */
    private function normalizeModuleName(string $module): ?string
    {
        // === PHP-specific handling ===
        // Skip PHP internal namespaces (App\, Tests\)
        if (str_starts_with($module, 'App\\') || str_starts_with($module, 'Tests\\')) {
            return null;
        }

        // PHP: Map Illuminate namespace to Laravel
        if (str_starts_with($module, 'Illuminate\\')) {
            return 'laravel/framework';
        }

        // PHP: Symfony namespace
        if (str_starts_with($module, 'Symfony\\')) {
            return 'Symfony';
        }

        // PHP namespaces (backslash separator)
        // GuzzleHttp\Client -> GuzzleHttp, Monolog\Logger -> Monolog
        if (str_contains($module, '\\')) {
            return explode('\\', $module)[0];
        }

        // === Dart-specific handling ===
        // Dart: package:flutter/material.dart -> flutter
        if (str_starts_with($module, 'package:')) {
            $withoutPrefix = mb_substr($module, 8); // Remove 'package:'
            $parts = explode('/', $withoutPrefix);

            return $parts[0];
        }

        // === Rust/Perl/R-specific handling ===
        // Rust: tokio::sync::mpsc -> tokio
        // Perl: Some::Module::Name -> Some
        // R: dplyr::filter -> dplyr
        if (str_contains($module, '::')) {
            $root = explode('::', $module)[0];

            return in_array($root, self::RUST_STD_MODULES, true) ? null : $root;
        }

        // === Clojure-specific handling ===
        // Clojure: clojure.core/map -> clojure.core -> clojure
        // But NOT Go URLs which contain slashes after dots (github.com/user/repo)
        if (str_contains($module, '/') && ! preg_match('/^[a-z]+\.[a-z]+\//', $module)) {
            // This is likely a Clojure qualified symbol
            $namespace = explode('/', $module)[0];

            // Extract root from namespace if it has dots
            if (str_contains($namespace, '.')) {
                return explode('.', $namespace)[0];
            }

            return $namespace;
        }

        // === Go-specific handling ===
        // Go: github.com/gin-gonic/gin -> keep full path (matches go.mod)
        // Identified by URL-like pattern
        if (preg_match('/^[a-z]+\.[a-z]+\//', $module)) {
            return $module;
        }

        // === Python/Java/C#/Scala/Kotlin/Elixir/Haskell/OCaml/Julia/Lua ===
        // All use dot notation: django.http -> django, com.example.Class -> com
        // Phoenix.Controller -> Phoenix, Data.List -> Data
        if (str_contains($module, '.')) {
            $root = explode('.', $module)[0];

            return in_array($root, self::STD_LIB_ROOTS, true) ? null : $root;
        }

        // === Simple names (Ruby, Swift, JS, etc.) ===
        // react, rails, Foundation, UIKit -> keep as-is
        return $module;
    }

    /**
     * Check if a dependency matches any of the imported modules.
     *
     * @param  array{name: string, version: string, dev?: bool}  $dependency
     * @param  array<string>  $importedModules
     */
    private function dependencyMatchesImport(array $dependency, array $importedModules): bool
    {
        $name = $dependency['name'];
        $nameLower = mb_strtolower($name);

        foreach ($importedModules as $module) {
            $moduleLower = mb_strtolower($module);

            // Exact match
            if ($nameLower === $moduleLower) {
                return true;
            }

            // Package name contains module (for things like "laravel/framework" matching "laravel/framework")
            if (str_contains($nameLower, $moduleLower)) {
                return true;
            }

            // Module name contains package (for things like "@angular/core" matching "angular")
            if (str_contains($moduleLower, $nameLower)) {
                return true;
            }

            // For composer packages, check if the package name (after /) matches
            // e.g., "laravel/framework" should match import "Illuminate" (handled by normalization)
            // But also "guzzlehttp/guzzle" should match import "GuzzleHttp"
            if (str_contains($name, '/')) {
                $parts = explode('/', $name);
                $packageName = end($parts);
                if (mb_strtolower($packageName) === $moduleLower) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Limit dependencies to a reasonable number, prioritizing those actually used.
     *
     * @param  array<int, array{name: string, version: string, dev?: bool}>  $dependencies
     * @param  array<string>  $importedModules
     * @return array<int, array{name: string, version: string, dev?: bool}>
     */
    private function limitDependencies(array $dependencies, array $importedModules = []): array
    {
        $main = [];
        $dev = [];

        foreach ($dependencies as $dep) {
            if (isset($dep['dev']) && $dep['dev']) {
                $dev[] = $dep;
            } else {
                $main[] = $dep;
            }
        }

        // If we have imported modules, prioritize dependencies that are used
        if ($importedModules !== []) {
            $main = $this->sortByUsage($main, $importedModules);
            $dev = $this->sortByUsage($dev, $importedModules);
        }

        $main = array_slice($main, 0, self::MAX_MAIN_DEPENDENCIES);
        $dev = array_slice($dev, 0, self::MAX_DEV_DEPENDENCIES);

        return array_merge($main, $dev);
    }

    /**
     * Sort dependencies by usage - used dependencies first.
     *
     * @param  array<int, array{name: string, version: string, dev?: bool}>  $dependencies
     * @param  array<string>  $importedModules
     * @return array<int, array{name: string, version: string, dev?: bool}>
     */
    private function sortByUsage(array $dependencies, array $importedModules): array
    {
        $used = [];
        $unused = [];

        foreach ($dependencies as $dep) {
            if ($this->dependencyMatchesImport($dep, $importedModules)) {
                $used[] = $dep;
            } else {
                $unused[] = $dep;
            }
        }

        return array_merge($used, $unused);
    }
}
