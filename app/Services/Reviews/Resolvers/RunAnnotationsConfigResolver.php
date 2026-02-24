<?php

declare(strict_types=1);

namespace App\Services\Reviews\Resolvers;

use App\Models\Run;
use App\Services\Reviews\ValueObjects\AnnotationConfig;

/**
 * Resolves annotation posting configuration from run policy snapshot.
 */
final class RunAnnotationsConfigResolver
{
    /**
     * Resolve annotation config from a run's policy snapshot.
     */
    public function resolve(Run $run): AnnotationConfig
    {
        $policy = $run->policy_snapshot ?? [];
        $annotations = is_array($policy['annotations'] ?? null) ? $policy['annotations'] : [];

        return new AnnotationConfig(
            style: is_string($annotations['style'] ?? null) ? $annotations['style'] : 'review',
            postThreshold: is_string($annotations['post_threshold'] ?? null) ? $annotations['post_threshold'] : 'medium',
            grouped: (bool) ($annotations['grouped'] ?? true),
            includeSuggestions: (bool) ($annotations['include_suggestions'] ?? true),
        );
    }
}
