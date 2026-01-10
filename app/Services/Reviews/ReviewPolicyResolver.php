<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Models\Repository;

final readonly class ReviewPolicyResolver
{
    /**
     * Resolve the review policy for a repository.
     *
     * @return array<string, mixed>
     */
    public function resolve(Repository $repository): array
    {
        $repository->loadMissing('settings');

        /** @var array<string, mixed> $defaultPolicy */
        $defaultPolicy = config('reviews.default_policy', []);

        $settings = $repository->settings;
        $repositoryRules = $settings !== null ? $settings->review_rules : [];

        if (! is_array($repositoryRules)) {
            $repositoryRules = [];
        }

        /** @var array<string, mixed> $result */
        $result = array_replace_recursive($defaultPolicy, $repositoryRules);

        return $result;
    }
}
