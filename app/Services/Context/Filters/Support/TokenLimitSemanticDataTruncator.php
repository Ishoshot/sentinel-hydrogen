<?php

declare(strict_types=1);

namespace App\Services\Context\Filters\Support;

/**
 * Truncates semantic analysis payloads to fit a token budget.
 */
final readonly class TokenLimitSemanticDataTruncator
{
    /**
     * Create a new semantic data truncator instance.
     */
    public function __construct(private AbstractTokenTruncator $tokenTruncator) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function truncate(array $data, int $maxTokens): array
    {
        $result = [];

        if (isset($data['language'])) {
            $result['language'] = $data['language'];
        }

        if (isset($data['functions']) && is_array($data['functions'])) {
            $result['functions'] = array_slice($data['functions'], 0, 5);
        }

        if (isset($data['classes']) && is_array($data['classes'])) {
            $result['classes'] = $this->truncateClasses($data['classes']);
        }

        if (isset($data['imports']) && is_array($data['imports'])) {
            $result['imports'] = array_slice($data['imports'], 0, 5);
        }

        if ($this->tokenTruncator->estimateTokens(json_encode($result) ?: '') <= $maxTokens) {
            return $result;
        }

        return [
            'language' => $data['language'] ?? 'unknown',
            'functions' => array_slice($data['functions'] ?? [], 0, 2),
            'classes' => array_slice($data['classes'] ?? [], 0, 1),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $classes
     * @return array<int, array<string, mixed>>
     */
    private function truncateClasses(array $classes): array
    {
        $classes = array_slice($classes, 0, 3);

        foreach ($classes as &$class) {
            if (! isset($class['methods']) || ! is_array($class['methods'])) {
                continue;
            }

            $class['methods'] = array_slice($class['methods'], 0, 5);
        }
        unset($class);

        return $classes;
    }
}
