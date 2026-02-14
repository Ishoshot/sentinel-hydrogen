<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class RubyManifestParser
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
    public function parseGemfile(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/ruby\s+["\']([^"\']+)["\']/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Ruby', 'version' => $matches[1]];
        }

        if (preg_match_all('/gem\s+["\']([^"\']+)["\'](?:,\s*["\']([^"\']+)["\'])?/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                    $result,
                    ['name' => $match[1], 'version' => $match[2] ?? '*'],
                    'ruby'
                );
            }
        }

        return $result;
    }
}
