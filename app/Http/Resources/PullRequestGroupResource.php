<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * Resource for grouped runs by pull request.
 *
 * @property-read int $pull_request_number
 * @property-read string|null $pull_request_title
 * @property-read mixed $repository
 * @property-read int $runs_count
 * @property-read mixed $latest_run
 * @property-read string $latest_status
 * @property-read \Illuminate\Support\Collection<int, \App\Models\Run> $runs
 */
final class PullRequestGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'pull_request_number' => $this->pull_request_number,
            'pull_request_title' => $this->pull_request_title,
            'repository' => new RepositoryResource($this->repository),
            'runs_count' => $this->runs_count,
            'latest_run' => new RunResource($this->latest_run),
            'latest_status' => $this->latest_status,
            'runs' => RunResource::collection($this->runs),
        ];
    }
}
