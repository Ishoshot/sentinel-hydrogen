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

final class Antigravity extends Agent implements SupportsGuidelines, SupportsMcp, SupportsSkills
{
    /**
     * Get the environment identifier.
     */
    public function name(): string
    {
        return 'antigravity';
    }

    /**
     * Get the human-readable name for the environment.
     */
    public function displayName(): string
    {
        return 'Antigravity';
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
                'command' => 'command -v antigravity',
            ],
            Platform::Windows => [
                'command' => 'where antigravity 2>null',
            ],
        };
    }

    /**
     * Antigravity reads MCP config from files.
     */
    #[Override]
    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    /**
     * Detect Antigravity usage in the project.
     *
     * @return array{paths: array<int, string>}
     */
    public function projectDetectionConfig(): array
    {
        return [
            'paths' => [
                '.agent',
                '.agent/rules',
                '.agent/skills',
            ],
        ];
    }

    /**
     * Path to workspace rules (guidelines).
     *
     * Antigravity applies rules from `.agent/rules`.
     */
    public function guidelinesPath(): string
    {
        return '.agent/rules/ANTIGRAVITY.md';
    }

    /**
     * Path to workspace skills.
     */
    public function skillsPath(): string
    {
        return '.agent/skills';
    }
}
