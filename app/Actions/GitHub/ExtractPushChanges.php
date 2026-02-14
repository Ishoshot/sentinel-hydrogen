<?php

declare(strict_types=1);

namespace App\Actions\GitHub;

final class ExtractPushChanges
{
    private const string CONFIG_PATH_PREFIX = '.sentinel/';

    /**
     * @param  array<string, mixed>  $payload
     * @return array{added: array<string>, modified: array<string>, removed: array<string>}
     */
    public function files(array $payload): array
    {
        $added = [];
        $modified = [];
        $removed = [];

        $commits = $payload['commits'] ?? [];

        if (! is_array($commits)) {
            return ['added' => [], 'modified' => [], 'removed' => []];
        }

        foreach ($commits as $commit) {
            if (! is_array($commit)) {
                continue;
            }

            $commitAdded = $commit['added'] ?? [];
            $commitModified = $commit['modified'] ?? [];
            $commitRemoved = $commit['removed'] ?? [];

            if (is_array($commitAdded)) {
                foreach ($commitAdded as $file) {
                    if (is_string($file)) {
                        $added[] = $file;
                    }
                }
            }

            if (is_array($commitModified)) {
                foreach ($commitModified as $file) {
                    if (is_string($file)) {
                        $modified[] = $file;
                    }
                }
            }

            if (is_array($commitRemoved)) {
                foreach ($commitRemoved as $file) {
                    if (is_string($file)) {
                        $removed[] = $file;
                    }
                }
            }
        }

        return [
            'added' => array_unique($added),
            'modified' => array_unique($modified),
            'removed' => array_unique($removed),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasConfigChanges(array $payload): bool
    {
        $commits = $payload['commits'] ?? [];

        if (! is_array($commits)) {
            return false;
        }

        foreach ($commits as $commit) {
            if (! is_array($commit)) {
                continue;
            }

            foreach (['added', 'modified', 'removed'] as $changeType) {
                $files = $commit[$changeType] ?? [];

                if (! is_array($files)) {
                    continue;
                }

                foreach ($files as $file) {
                    if (is_string($file) && str_starts_with($file, self::CONFIG_PATH_PREFIX)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
