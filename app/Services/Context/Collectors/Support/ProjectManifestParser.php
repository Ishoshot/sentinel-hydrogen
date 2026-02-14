<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\Context\Collectors\Support\ManifestParsers\ComposerJsonManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\DartManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\ElixirManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\GoManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\JavaManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\PackageJsonManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\PythonManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\RubyManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\RustManifestParser;
use Closure;

/**
 * Parses ecosystem manifest files into a normalized project context shape.
 */
final readonly class ProjectManifestParser
{
    /**
     * @var array<string, Closure(string): ?array{
     *     runtime?: array{name: string, version: string},
     *     frameworks?: array<int, array{name: string, version: string}>,
     *     dependencies?: array<int, array{name: string, version: string, dev?: bool}>
     * }>
     */
    private array $manifestParsers;

    /**
     * Create a new instance.
     */
    public function __construct(
        private ProjectManifestDependencyRegistry $dependencyRegistry = new ProjectManifestDependencyRegistry,
        ?ComposerJsonManifestParser $composerJsonParser = null,
        ?PackageJsonManifestParser $packageJsonParser = null,
        ?PythonManifestParser $pythonManifestParser = null,
        ?GoManifestParser $goManifestParser = null,
        ?RustManifestParser $rustManifestParser = null,
        ?RubyManifestParser $rubyManifestParser = null,
        ?DartManifestParser $dartManifestParser = null,
        ?ElixirManifestParser $elixirManifestParser = null,
        ?JavaManifestParser $javaManifestParser = null,
    ) {
        $composerJsonParser ??= new ComposerJsonManifestParser($this->dependencyRegistry);
        $packageJsonParser ??= new PackageJsonManifestParser($this->dependencyRegistry);
        $pythonManifestParser ??= new PythonManifestParser($this->dependencyRegistry);
        $goManifestParser ??= new GoManifestParser($this->dependencyRegistry);
        $rustManifestParser ??= new RustManifestParser($this->dependencyRegistry);
        $rubyManifestParser ??= new RubyManifestParser($this->dependencyRegistry);
        $dartManifestParser ??= new DartManifestParser($this->dependencyRegistry);
        $elixirManifestParser ??= new ElixirManifestParser($this->dependencyRegistry);
        $javaManifestParser ??= new JavaManifestParser($this->dependencyRegistry);

        $this->manifestParsers = [
            'composer.json' => fn (string $content): ?array => $composerJsonParser->parse($content),
            'package.json' => fn (string $content): ?array => $packageJsonParser->parse($content),
            'go.mod' => fn (string $content): array => $goManifestParser->parse($content),
            'Cargo.toml' => fn (string $content): array => $rustManifestParser->parseCargoToml($content),
            'pyproject.toml' => fn (string $content): array => $pythonManifestParser->parsePyprojectToml($content),
            'requirements.txt' => fn (string $content): array => $pythonManifestParser->parseRequirementsTxt($content),
            'Gemfile' => fn (string $content): array => $rubyManifestParser->parseGemfile($content),
            'pubspec.yaml' => fn (string $content): array => $dartManifestParser->parsePubspecYaml($content),
            'mix.exs' => fn (string $content): array => $elixirManifestParser->parseMixExs($content),
            'pom.xml' => fn (string $content): array => $javaManifestParser->parsePomXml($content),
            'build.gradle' => fn (string $content): array => $javaManifestParser->parseGradleBuild($content),
            'build.gradle.kts' => fn (string $content): array => $javaManifestParser->parseGradleBuild($content),
        ];
    }

    /**
     * Parse a manifest file based on its type.
     *
     * @return array{
     *     runtime?: array{name: string, version: string},
     *     frameworks?: array<int, array{name: string, version: string}>,
     *     dependencies?: array<int, array{name: string, version: string, dev?: bool}>
     * }|null
     */
    public function parseManifest(string $filename, string $content): ?array
    {
        $parser = $this->manifestParsers[$filename] ?? null;

        if ($parser === null) {
            return null;
        }

        return $parser($content);
    }
}
