<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class PackageJsonManifestParser
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
        /** @var array{engines?: array{node?: string}, dependencies?: array<string, string>, devDependencies?: array<string, string>}|null $json */
        $json = json_decode($content, true);

        if (! is_array($json)) {
            return null;
        }

        $result = $this->dependencyRegistry->emptyResult();

        if (isset($json['engines']['node'])) {
            $result['runtime'] = ['name' => 'Node.js', 'version' => $json['engines']['node']];
        }

        foreach ($json['dependencies'] ?? [] as $package => $version) {
            $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                $result,
                ['name' => $package, 'version' => $version],
                'javascript'
            );
        }

        foreach ($json['devDependencies'] ?? [] as $package => $version) {
            $this->dependencyRegistry->addDependencyWithFrameworkDetection(
                $result,
                ['name' => $package, 'version' => $version],
                'javascript',
                isDev: true
            );
        }

        return $result;
    }
}
