<?php

declare(strict_types=1);

namespace App\Services\SentinelConfig;

use App\DataTransferObjects\SentinelConfig\TriggersConfig;

/**
 * Evaluates trigger rules to determine if a review should run.
 */
final readonly class TriggerRuleEvaluator
{
    /**
     * Evaluate trigger rules and return whether review should proceed.
     *
     * @param  array{base_branch: string, head_branch: string, author_login: string, labels: array<int, string>}  $context
     * @return array{should_trigger: bool, reason: ?string}
     */
    public function evaluate(TriggersConfig $config, array $context): array
    {
        // Check target branch filter
        if (! $this->matchesAnyPattern($context['base_branch'], $config->targetBranches)) {
            return [
                'should_trigger' => false,
                'reason' => sprintf(
                    'Target branch "%s" does not match allowed patterns: %s',
                    $context['base_branch'],
                    implode(', ', $config->targetBranches)
                ),
            ];
        }

        // Check skip source branches (empty array means skip none)
        if ($config->skipSourceBranches !== [] && $this->matchesAnyPattern($context['head_branch'], $config->skipSourceBranches)) {
            return [
                'should_trigger' => false,
                'reason' => sprintf(
                    'Source branch "%s" matches skip pattern',
                    $context['head_branch']
                ),
            ];
        }

        // Check skip labels
        foreach ($context['labels'] as $label) {
            if (in_array($label, $config->skipLabels, true)) {
                return [
                    'should_trigger' => false,
                    'reason' => sprintf('PR has skip label: %s', $label),
                ];
            }
        }

        // Check skip authors
        if (in_array($context['author_login'], $config->skipAuthors, true)) {
            return [
                'should_trigger' => false,
                'reason' => sprintf('Author "%s" is in skip list', $context['author_login']),
            ];
        }

        return [
            'should_trigger' => true,
            'reason' => null,
        ];
    }

    /**
     * Check if a value matches any of the given patterns.
     *
     * Supports glob-style wildcards:
     * - * matches any sequence of characters
     * - ? matches any single character
     *
     * @param  array<int, string>  $patterns
     */
    private function matchesAnyPattern(string $value, array $patterns): bool
    {
        if ($patterns === []) {
            return true;
        }

        return array_any($patterns, fn (string $pattern): bool => $this->matchesPattern($value, $pattern));
    }

    /**
     * Check if a value matches a glob-style pattern.
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        // Exact match
        if ($value === $pattern) {
            return true;
        }

        // Convert glob pattern to regex
        $regex = $this->globToRegex($pattern);

        return preg_match($regex, $value) === 1;
    }

    /**
     * Convert a glob pattern to a regex pattern.
     */
    private function globToRegex(string $pattern): string
    {
        // Escape special regex characters except * and ?
        $escaped = preg_quote($pattern, '/');

        // Convert glob wildcards to regex equivalents
        // \* becomes .* (match any sequence)
        // \? becomes . (match single character)
        $regex = str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            $escaped
        );

        return '/^'.$regex.'$/';
    }
}
