<?php

declare(strict_types=1);

namespace App\Boost;

use Laravel\Boost\Contracts\Agent;
use Laravel\Boost\Install\CodeEnvironment\CodeEnvironment;
use Laravel\Boost\Install\Enums\Platform;
use Override;

final class Windsurf extends CodeEnvironment implements Agent
{
    /**
     * Get the environment identifier.
     */
    public function name(): string
    {
        return 'windsurf';
    }

    /**
     * Get the human-readable name for the environment.
     */
    public function displayName(): string
    {
        return 'Windsurf';
    }

    /**
     * Get platform-specific detection paths.
     *
     * @return array{paths: array<int, string>}
     */
    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin => [
                'paths' => ['/Applications/Windsurf.app'],
            ],
            Platform::Linux => [
                'paths' => [
                    '/opt/windsurf',
                    '/usr/local/bin/windsurf',
                    '~/.local/bin/windsurf',
                    '/snap/bin/windsurf',
                ],
            ],
            Platform::Windows => [
                'paths' => [
                    '%ProgramFiles%\\Windsurf',
                    '%LOCALAPPDATA%\\Programs\\Windsurf',
                    '%APPDATA%\\Windsurf',
                ],
            ],
        };
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
        ];
    }

    #[Override]
    /**
     * Get the agent display name.
     */
    public function agentName(): string
    {
        return 'Cascade';
    }

    /**
     * Get the path to the Boost guidelines.
     */
    public function guidelinesPath(): string
    {
        return '.windsurf/rules/laravel-boost.md';
    }
}
