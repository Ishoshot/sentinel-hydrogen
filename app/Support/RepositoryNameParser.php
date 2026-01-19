<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Parses repository full names in "owner/repo" format.
 */
final class RepositoryNameParser
{
    /**
     * Parse a repository full name into owner and repo components.
     *
     * @param  string  $fullName  The full repository name (e.g., "owner/repo")
     * @return array{owner: string, repo: string}|null Returns null if format is invalid
     */
    public static function parse(string $fullName): ?array
    {
        $parts = explode('/', $fullName);

        if (count($parts) !== 2) {
            return null;
        }

        [$owner, $repo] = $parts;

        if ($owner === '' || $repo === '') {
            return null;
        }

        return [
            'owner' => $owner,
            'repo' => $repo,
        ];
    }
}
