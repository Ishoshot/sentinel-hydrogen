<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

/**
 * Scores file relevance based on path patterns and change metrics.
 */
final readonly class RelevanceScorer
{
    /**
     * File patterns that are considered high priority.
     *
     * @var array<string, int>
     */
    private const array PRIORITY_PATTERNS = [
        // Core application files (highest priority)
        '/^app\//' => 100,
        '/^src\//' => 100,
        '/^lib\//' => 90,

        // Configuration files
        '/^config\//' => 80,
        '/\.env\.example$/' => 70,

        // Database changes
        '/^database\/migrations\//' => 85,
        '/^database\/factories\//' => 60,
        '/^database\/seeders\//' => 50,

        // Routes
        '/^routes\//' => 75,

        // Tests (medium priority - important but secondary to source)
        '/^tests\//' => 65,
        '/\.test\.(ts|js|tsx|jsx)$/' => 65,
        '/\.spec\.(ts|js|tsx|jsx)$/' => 65,
        '/Test\.php$/' => 65,

        // Frontend source
        '/^resources\//' => 70,
        '/^components\//' => 70,
        '/^pages\//' => 70,

        // Documentation (lower priority)
        '/\.md$/i' => 30,
        '/^docs\//' => 25,

        // Package files
        '/^composer\.json$/' => 55,
        '/^package\.json$/' => 55,
        '/^composer\.lock$/' => 20,
        '/^package-lock\.json$/' => 15,
        '/^yarn\.lock$/' => 15,
        '/^pnpm-lock\.yaml$/' => 15,

        // Build/tooling config (low priority)
        '/^\.github\//' => 35,
        '/^\.circleci\//' => 35,
        '/^Dockerfile/' => 40,
        '/^docker-compose/' => 40,
        '/phpstan\./' => 30,
        '/phpunit\./' => 30,
        '/eslint/' => 25,
        '/prettier/' => 20,
    ];

    /**
     * Calculate a relevance score for a file.
     *
     * @param  array{filename: string, status: string, additions: int, deletions: int, changes: int, patch: string|null}  $file
     */
    public function score(array $file): int
    {
        $filename = $file['filename'];
        $changes = $file['additions'] + $file['deletions'];

        // Base score from pattern matching
        $patternScore = $this->getPatternScore($filename);

        // Boost score based on change size (more changes = more important)
        // Use log scale to prevent huge diffs from dominating
        $changeBoost = (int) min(30, log($changes + 1, 2) * 5);

        // Penalize very small changes (likely formatting or typo fixes)
        $smallChangePenalty = $changes <= 2 ? -10 : 0;

        // Boost files with patches (actual diff content)
        $patchBoost = $file['patch'] !== null ? 15 : 0;

        return $patternScore + $changeBoost + $smallChangePenalty + $patchBoost;
    }

    /**
     * Get priority score based on file path patterns.
     */
    private function getPatternScore(string $filename): int
    {
        foreach (self::PRIORITY_PATTERNS as $pattern => $score) {
            if (preg_match($pattern, $filename) === 1) {
                return $score;
            }
        }

        // Default score for unmatched files
        return 50;
    }
}
