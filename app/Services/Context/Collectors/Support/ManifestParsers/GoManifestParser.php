<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class GoManifestParser
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
    public function parse(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/^go\s+(\d+\.\d+(?:\.\d+)?)/m', $content, $matches)) {
            $result['runtime'] = ['name' => 'Go', 'version' => $matches[1]];
        }

        /** @var array<string, bool> $seenPackages */
        $seenPackages = [];

        if (preg_match_all('/^\s*require\s+([^\s]+)\s+([^\s]+)/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $seenPackages[$match[1]] = true;
                $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                    $result,
                    ['name' => $match[1], 'version' => $match[2]],
                    'go'
                );
            }
        }

        if (preg_match('/require\s*\(\s*(.*?)\s*\)/s', $content, $blockMatch) && preg_match_all('/^\s*([^\s]+)\s+([^\s]+)/m', $blockMatch[1], $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($seenPackages[$match[1]])) {
                    continue;
                }

                $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                    $result,
                    ['name' => $match[1], 'version' => $match[2]],
                    'go'
                );
            }
        }

        return $result;
    }
}
