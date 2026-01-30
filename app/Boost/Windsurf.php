<?php

declare(strict_types=1);

namespace App\Boost;

use Laravel\Boost\Contracts\SupportsGuidelines;
use Laravel\Boost\Contracts\SupportsMcp;
use Laravel\Boost\Contracts\SupportsSkills;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\Enums\McpInstallationStrategy;
use Laravel\Boost\Install\Enums\Platform;
use Override;

final class Windsurf extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Get the environment identifier.
     */
    public function name(): string
    {
        return 'cascade';
    }

    /**
     * Get the human-readable name for the environment.
     */
    public function displayName(): string
    {
        return 'Cascade (Windsurf)';
    }

    /**
     * Get platform-specific detection command.
     *
     * @return array{command: string}
     */
    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v windsurf',
            ],
            Platform::Windows => [
                'command' => 'where windsurf 2>null',
            ],
        };
    }

    /**
     * Get the MCP installation strategy.
     */
    #[Override]
    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    /**
     * Get project detection configuration.
     *
     * @return array{paths: array<int, string>}
     */
    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.windsurf'],
            'files' => ['CASCADE.md'],
        ];
    }

    /**
     * Get the path to the Boost guidelines.
     */
    public function guidelinesPath(): string
    {
        return '.windsurf/rules/CASCADE.md';
    }

    /**
     * Get the path to the skills directory.
     */
    public function skillsPath(): string
    {
        return '.claude/skills';
    }
}
