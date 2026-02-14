<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class ComposerJsonManifestParser
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private ProjectManifestDependencyRegistry $dependencyRegistry,
    ) {}

    /**
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}|null
     */
    public function parse(string $content): ?array
    {
        /** @var array{require?: array<string, string>, require-dev?: array<string, string>}|null $json */
        $json = json_decode($content, true);

        if (! is_array($json)) {
            return null;
        }

        $result = $this->dependencyRegistry->emptyResult();

        $require = $json['require'] ?? [];
        if (isset($require['php'])) {
            $result['runtime'] = ['name' => 'PHP', 'version' => $require['php']];
        }

        foreach ($require as $package => $version) {
            if ($package === 'php') {
                continue;
            }

            if (str_starts_with($package, 'ext-')) {
                continue;
            }

            $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                $result,
                ['name' => $package, 'version' => $version],
                'php'
            );
        }

        foreach ($json['require-dev'] ?? [] as $package => $version) {
            $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                $result,
                ['name' => $package, 'version' => $version],
                'php',
                isDev: true
            );
        }

        return $result;
    }
}
