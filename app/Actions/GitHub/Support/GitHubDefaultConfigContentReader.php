<?php

declare(strict_types=1);

namespace App\Actions\GitHub\Support;

use RuntimeException;

final class GitHubDefaultConfigContentReader
{
    /**
     * Load the default Sentinel configuration content.
     */
    public function read(): string
    {
        $exampleConfigPath = base_path('.sentinel/config.example.yaml');
        $content = file_get_contents($exampleConfigPath);

        if ($content === false) {
            throw new RuntimeException('Failed to read example config file');
        }

        return $content;
    }
}
