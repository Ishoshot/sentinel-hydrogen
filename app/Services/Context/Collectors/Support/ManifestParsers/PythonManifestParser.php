<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class PythonManifestParser
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private ProjectManifestDependencyRegistry $dependencyRegistry,
    ) {}

    /**
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function parsePyprojectToml(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/requires-python\s*=\s*"([^"]+)"/i', $content, $matches)) {
            $result['runtime'] = ['name' => 'Python', 'version' => $matches[1]];
        }

        if (preg_match('/dependencies\s*=\s*\[(.*?)\]/s', $content, $depsMatch) && preg_match_all('/"([^"]+)"/s', $depsMatch[1], $packages)) {
            foreach ($packages[1] as $package) {
                $parsed = $this->parseDependency($package);
                if ($parsed !== null) {
                    $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, $parsed, 'python');
                }
            }
        }

        return $result;
    }

    /**
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function parseRequirementsTxt(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        foreach (explode("\n", $content) as $line) {
            $line = mb_trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, '-')) {
                continue;
            }

            $parsed = $this->parseDependency($line);
            if ($parsed === null) {
                continue;
            }

            $this->dependencyRegistry->addDependencyWithFrameworkDetection($result, $parsed, 'python');
        }

        return $result;
    }

    /**
     * @return array{name: string, version: string}|null
     */
    private function parseDependency(string $dependency): ?array
    {
        if (preg_match('/^([a-zA-Z0-9_-]+)(?:\[.*?\])?([<>=!~]+)(.+)$/', $dependency, $matches)) {
            return ['name' => $matches[1], 'version' => $matches[2].$matches[3]];
        }

        if (preg_match('/^([a-zA-Z0-9_-]+)(?:\[.*?\])?$/', $dependency, $matches)) {
            return ['name' => $matches[1], 'version' => '*'];
        }

        return null;
    }
}
