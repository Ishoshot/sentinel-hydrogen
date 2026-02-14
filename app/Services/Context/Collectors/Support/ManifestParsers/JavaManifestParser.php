<?php

declare(strict_types=1);

namespace App\Services\Context\Collectors\Support\ManifestParsers;

use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

final readonly class JavaManifestParser
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
    public function parsePomXml(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/<java\.version>([^<]+)</', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        } elseif (preg_match('/<maven\.compiler\.source>([^<]+)</', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        }

        if (str_contains($content, 'spring-boot')) {
            if (preg_match('/<spring-boot\.version>([^<]+)</', $content, $matches)) {
                $result['frameworks'][] = ['name' => 'Spring Boot', 'version' => $matches[1]];
            } else {
                $result['frameworks'][] = ['name' => 'Spring Boot', 'version' => '*'];
            }
        }

        if (preg_match_all('/<dependency>.*?<groupId>([^<]+)<.*?<artifactId>([^<]+)<.*?(?:<version>([^<]+)<)?.*?<\/dependency>/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result['dependencies'][] = [
                    'name' => $match[1].'/'.$match[2],
                    'version' => $match[3] ?? '*',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{runtime?: array{name: string, version: string}, frameworks?: array<int, array{name: string, version: string}>, dependencies?: array<int, array{name: string, version: string, dev?: bool}>}
     */
    public function parseGradleBuild(string $content): array
    {
        $result = $this->dependencyRegistry->emptyResult();

        if (preg_match('/sourceCompatibility\s*[=:]\s*[\'"]?(\d+)[\'"]?/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        } elseif (preg_match('/JavaLanguageVersion\.of\((\d+)\)/', $content, $matches)) {
            $result['runtime'] = ['name' => 'Java', 'version' => $matches[1]];
        }

        if (str_contains($content, 'spring-boot')) {
            $result['frameworks'][] = ['name' => 'Spring Boot', 'version' => '*'];
        }

        if (preg_match_all('/(?:implementation|api|compile|testImplementation)\s*[(\s][\'"]([^:]+):([^:]+):([^\'"]+)[\'"]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result['dependencies'][] = [
                    'name' => $match[1].'/'.$match[2],
                    'version' => $match[3],
                ];
            }
        }

        return $result;
    }
}
