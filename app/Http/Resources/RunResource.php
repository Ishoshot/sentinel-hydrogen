<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Run
 */
final class RunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repository_id' => $this->repository_id,
            'external_reference' => $this->external_reference,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'findings_count' => $this->whenCounted('findings', $this->findings_count),
            'metrics' => $this->metrics,
            'policy_snapshot' => $this->policy_snapshot,
            'pull_request' => $this->formatPullRequest(),
            'summary' => $this->formatSummary(),
            'metadata' => $this->metadata,
            'repository' => new RepositoryResource($this->whenLoaded('repository')),
            'findings' => FindingResource::collection($this->whenLoaded('findings')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    /**
     * Format the pull request data from metadata.
     *
     * @return array<string, mixed>|null
     */
    private function formatPullRequest(): ?array
    {
        $metadata = $this->metadata;

        if (! is_array($metadata) || ! isset($metadata['pull_request_number'])) {
            return null;
        }

        return [
            'number' => $metadata['pull_request_number'],
            'title' => $metadata['pull_request_title'] ?? null,
            'body' => $metadata['pull_request_body'] ?? null,
            'base_branch' => $metadata['base_branch'] ?? null,
            'head_branch' => $metadata['head_branch'] ?? null,
            'head_sha' => $metadata['head_sha'] ?? null,
            'is_draft' => $metadata['is_draft'] ?? false,
            'author' => $metadata['author'] ?? [
                'login' => $metadata['sender_login'] ?? null,
                'avatar_url' => null,
            ],
            'assignees' => $metadata['assignees'] ?? [],
            'reviewers' => $metadata['reviewers'] ?? [],
            'labels' => $metadata['labels'] ?? [],
        ];
    }

    /**
     * Format the review summary from metadata.
     *
     * @return array{overview: string, verdict: string, risk_level: string, strengths: array<int, string>, concerns: array<int, string>, recommendations: array<int, string>}|null
     */
    private function formatSummary(): ?array
    {
        $metadata = $this->metadata;

        if (! is_array($metadata) || ! isset($metadata['review_summary'])) {
            return null;
        }

        $summary = $metadata['review_summary'];

        if (! is_array($summary)) {
            return null;
        }

        return [
            'overview' => is_string($summary['overview'] ?? null) ? $summary['overview'] : '',
            'verdict' => is_string($summary['verdict'] ?? null) ? $summary['verdict'] : 'comment',
            'risk_level' => is_string($summary['risk_level'] ?? null) ? $summary['risk_level'] : 'low',
            'strengths' => $this->filterStringArray($summary['strengths'] ?? []),
            'concerns' => $this->filterStringArray($summary['concerns'] ?? []),
            'recommendations' => $this->filterStringArray($summary['recommendations'] ?? []),
        ];
    }

    /**
     * Filter an array to only include string values.
     *
     * @return array<int, string>
     */
    private function filterStringArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
