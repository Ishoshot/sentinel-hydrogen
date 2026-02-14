<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class ElixirManifestParser
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
    public function parseMixExs(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/elixir:\s*"([^"]+)"/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Elixir', 'version' => $matches[1]];
        }

        if (str_contains($content, ':phoenix')) {
            if (preg_match('/:phoenix,\s*"([^"]+)"/', $content, $matches)) {
                $result['frameworks'][] = ['name' => 'Phoenix', 'version' => $matches[1]];
            } else {
                $result['frameworks'][] = ['name' => 'Phoenix', 'version' => '*'];
            }
        }

        if (preg_match_all('/\{:([a-z_]+),\s*"([^"]+)"/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result['dependencies'][] = ['name' => $match[1], 'version' => $match[2]];
            }
        }

        return $result;
    }
}
