<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support;

use App\Services\Context\Collectors\Support\ManifestParsers\ComposerJsonManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\PackageJsonManifestParser;
use App\Services\Context\Collectors\Support\ManifestParsers\PythonManifestParser;

/**
 * Parses ecosystem manifest files into a normalized project context shape.
 */
final readonly class ProjectManifestParser
{
    /**
     * Parser for PHP Composer manifests.
     */
    private ComposerJsonManifestParser $composerJsonParser;

    /**
     * Parser for JavaScript package manifests.
     */
    private PackageJsonManifestParser $packageJsonParser;

    /**
     * Parser for Python dependency manifests.
     */
    private PythonManifestParser $pythonManifestParser;

    /**
     * Create a new instance.
     */
    public function __construct(
        private ProjectManifestDependencyRegistry $dependencyRegistry = new ProjectManifestDependencyRegistry,
        ?ComposerJsonManifestParser $composerJsonParser = null,
        ?PackageJsonManifestParser $packageJsonParser = null,
        ?PythonManifestParser $pythonManifestParser = null,
    ) {
        $this->composerJsonParser = $composerJsonParser ?? new ComposerJsonManifestParser($this->dependencyRegistry);
        $this->packageJsonParser = $packageJsonParser ?? new PackageJsonManifestParser($this->dependencyRegistry);
        $this->pythonManifestParser = $pythonManifestParser ?? new PythonManifestParser($this->dependencyRegistry);
    }

    /**
     * Parse a manifest file based on its type.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function parseManifest(string $filename, string $content): ?array
    {
        return match ($filename) {
            'composer.json' => $this->composerJsonParser->parse($content),
            'package.json' => $this->packageJsonParser->parse($content),
            'go.mod' => $this->parseGoMod($content),
            'Cargo.toml' => $this->parseCargoToml($content),
            'pyproject.toml' => $this->pythonManifestParser->parsePyprojectToml($content),
            'requirements.txt' => $this->pythonManifestParser->parseRequirementsTxt($content),
            'Gemfile' => $this->parseGemfile($content),
            'pubspec.yaml' => $this->parsePubspecYaml($content),
            'mix.exs' => $this->parseMixExs($content),
            'pom.xml' => $this->parsePomXml($content),
            'build.gradle', 'build.gradle.kts' => $this->parseGradleBuild($content),
            default => null,
        };
    }

    /**
     * Parse Go go.mod file.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseGoMod(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

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
                $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'go');
            }
        }

        // Also parse require block
        if (preg_match('/require\s*\(\s*(.*?)\s*\)/s', $content, $blockMatch) && preg_match_all('/^\s*([^\s]+)\s+([^\s]+)/m', $blockMatch[1], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($seen[$match[1]])) {
                    continue;
                }

                $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'go');
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
        $result = $this->dependencyRegistry->emptyResult();

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
                $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'rust', $isDev);
            }
        }

        // Complex format: package = { version = "..." }
        if (preg_match_all('/^([a-zA-Z0-9_-]+)\s*=\s*\{[^}]*version\s*=\s*"([^"]+)"/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2]], 'rust', $isDev);
            }
        }
    }

    /**
     * Parse Ruby Gemfile.
     *
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    private function parseGemfile(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        // Extract ruby version
        if (preg_match('/ruby\s+["\']([^"\']+)["\']/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Ruby', 'version' => $matches[1]];
        }

        // Extract gems: gem 'name', '~> version' or gem 'name'
        if (preg_match_all('/gem\s+["\']([^"\']+)["\'](?:,\s*["\']([^"\']+)["\'])?/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2] ?? '*'], 'ruby');
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
        $result = $this->dependencyRegistry->emptyResult();

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
                $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, ['name' => $match[1], 'version' => $match[2] ?? '*'], 'dart', $isDev);
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
        $result = $this->dependencyRegistry->emptyResult();

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
        $result = $this->dependencyRegistry->emptyResult();

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
        $result = $this->dependencyRegistry->emptyResult();

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
}
