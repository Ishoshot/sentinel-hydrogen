<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;
use stdClass;

/**
 * Resource for grouped runs by repository.
 *
 * @property-read mixed $repository
 * @property-read int $pull_requests_count
 * @property-read int $runs_count
 * @property-read \Illuminate\Support\Collection<int, stdClass> $pull_requests
 */
final class RepositoryGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'repository' => new RepositoryResource($this->repository),
            'pull_requests_count' => $this->pull_requests_count,
            'runs_count' => $this->runs_count,
            'pull_requests' => PullRequestGroupResource::collection($this->pull_requests),
        ];
    }
}
