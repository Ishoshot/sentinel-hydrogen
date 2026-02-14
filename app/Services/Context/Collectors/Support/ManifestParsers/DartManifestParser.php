<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class DartManifestParser
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
    public function parsePubspecYaml(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/sdk:\s*["\']?([^"\'\n]+)/i', $content, $matches)) {
            $result['runtime'] = ['name' => 'Dart', 'version' => mb_trim($matches[1])];
        }

        if (str_contains($content, 'flutter:')) {
            $result['frameworks'][] = ['name' => 'Flutter', 'version' => '*'];
        }

        if (preg_match('/^dependencies:\s*\n((?:\s+[^\n]+\n?)*)/m', $content, $depsMatch)) {
            $this->parseDependencies($depsMatch[1], $result, false);
        }

        if (preg_match('/^dev_dependencies:\s*\n((?:\s+[^\n]+\n?)*)/m', $content, $devMatch)) {
            $this->parseDependencies($devMatch[1], $result, true);
        }

        return $result;
    }

    /**
     * @param  array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}  $result
     */
    private function parseDependencies(string $section, array &$result, bool $isDev): void
    {
        if (preg_match_all('/^\s{2}([a-z_]+):\s*(?:\^|>=?|<)?([0-9.]+|\*|any)?/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                    $result,
                    ['name' => $match[1], 'version' => $match[2] ?? '*'],
                    'dart',
                    $isDev
                );
            }
        }
    }
}
