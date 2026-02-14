<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\AnnotationType;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Provider;
use App\Models\Run;
use Illuminate\Support\Collection;

final class StoreRunAnnotations
{
    /**
     * @param  Collection<int, Finding>  $findings
     * @param  array<string, mixed>  $reviewResponse
     */
    public function handle(Run $run, Collection $findings, array $reviewResponse): void
    {
        $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
        $reviewId = $reviewResponse['id'] ?? null;
        $externalId = is_scalar($reviewId) ? (string) $reviewId : null;

        foreach ($findings as $finding) {
            Annotation::query()->create([
                'finding_id' => $finding->id,
                'workspace_id' => $run->workspace_id,
                'provider_id' => $provider?->id,
                'external_id' => $externalId,
                'type' => AnnotationType::Inline->value,
                'created_at' => now(),
            ]);
        }
    }
}
