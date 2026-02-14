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
            'composer.json' => $composerJsonParser->parse(...),
            'package.json' => $packageJsonParser->parse(...),
            'go.mod' => $goManifestParser->parse(...),
            'Cargo.toml' => $rustManifestParser->parseCargoToml(...),
            'pyproject.toml' => $pythonManifestParser->parsePyprojectToml(...),
            'requirements.txt' => $pythonManifestParser->parseRequirementsTxt(...),
            'Gemfile' => $rubyManifestParser->parseGemfile(...),
            'pubspec.yaml' => $dartManifestParser->parsePubspecYaml(...),
            'mix.exs' => $elixirManifestParser->parseMixExs(...),
            'pom.xml' => $javaManifestParser->parsePomXml(...),
            'build.gradle' => $javaManifestParser->parseGradleBuild(...),
            'build.gradle.kts' => $javaManifestParser->parseGradleBuild(...),
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
