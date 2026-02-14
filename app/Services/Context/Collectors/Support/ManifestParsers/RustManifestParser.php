<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class RustManifestParser
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
    public function parseCargoToml(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/^rust-version\s*=\s*"([^"]+)"/m', $content, $matches)) {
            $result['runtime'] = ['name' => 'Rust', 'version' => $matches[1]];
        }

        if (preg_match('/^\[dependencies\]\s*$(.*?)(?=^\[|\z)/ms', $content, $depsMatch)) {
            $this->parseSection($depsMatch[1], $result, false);
        }

        if (preg_match('/^\[dev-dependencies\]\s*$(.*?)(?=^\[|\z)/ms', $content, $devMatch)) {
            $this->parseSection($devMatch[1], $result, true);
        }

        return $result;
    }

    /**
     * @param  array{frameworks: array<int, array{name: string, version: string}>, dependencies: array<int, array{name: string, version: string, dev?: bool}>}  $result
     */
    private function parseSection(string $section, array &$result, bool $isDev): void
    {
        if (preg_match_all('/^([a-zA-Z0-9_-]+)\s*=\s*"([^"]+)"/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                    $result,
                    ['name' => $match[1], 'version' => $match[2]],
                    'rust',
                    $isDev
                );
            }
        }

        if (preg_match_all('/^([a-zA-Z0-9_-]+)\s*=\s*\{[^}]*version\s*=\s*"([^"]+)"/m', $section, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                    $result,
                    ['name' => $match[1], 'version' => $match[2]],
                    'rust',
                    $isDev
                );
            }
        }
    }
}
