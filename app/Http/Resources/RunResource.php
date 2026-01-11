<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Run
 */
final class RunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
     * @return array{overview: string, risk_level: string, recommendations: array<int, string>}|null
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

        $recommendations = [];
        if (is_array($summary['recommendations'] ?? null)) {
            foreach ($summary['recommendations'] as $rec) {
                if (is_string($rec)) {
                    $recommendations[] = $rec;
                }
            }
        }

        return [
            'overview' => is_string($summary['overview'] ?? null) ? $summary['overview'] : '',
            'risk_level' => is_string($summary['risk_level'] ?? null) ? $summary['risk_level'] : 'low',
            'recommendations' => $recommendations,
        ];
    }
}
