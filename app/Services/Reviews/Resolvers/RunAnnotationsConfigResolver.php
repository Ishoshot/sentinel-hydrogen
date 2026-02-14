<?php

declare(strict_types=1);

namespace App\Services\Reviews\Resolvers;

use App\Models\Run;

/**
 * Resolves annotation posting configuration from run policy snapshot.
 */
final class RunAnnotationsConfigResolver
{
    /**
     * @return array{style: string, post_threshold: string, grouped: bool, include_suggestions: bool}
     */
    public function resolve(Run $run): array
    {
        $policy = $run->policy_snapshot ?? [];
        $annotations = is_array($policy['annotations'] ?? null) ? $policy['annotations'] : [];

        return [
            'style' => is_string($annotations['style'] ?? null) ? $annotations['style'] : 'review',
            'post_threshold' => is_string($annotations['post_threshold'] ?? null) ? $annotations['post_threshold'] : 'medium',
            'grouped' => (bool) ($annotations['grouped'] ?? true),
            'include_suggestions' => (bool) ($annotations['include_suggestions'] ?? true),
        ];
    }
}
