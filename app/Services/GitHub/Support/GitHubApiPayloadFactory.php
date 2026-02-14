<?php

declare(strict_types=1);

namespace App\Services\GitHub\Support;

final class GitHubApiPayloadFactory
{
    /**
     * @param  array<int, array{path: string, line: int, side: string, body: string}>  $comments
     * @return array{body: string, event: string, commit_id?: string, comments?: array<int, array{path: string, line: int, side: string, body: string}>}
     */
    public function pullRequestReview(string $body, string $event, array $comments, ?string $commitId): array
    {
        $payload = [
            'body' => $body,
            'event' => $event,
        ];

        if ($commitId !== null) {
            $payload['commit_id'] = $commitId;
        }

        if ($comments !== []) {
            $payload['comments'] = $comments;
        }

        return $payload;
    }

    /**
     * @param  array<int, array{path: string, start_line: int, end_line: int, annotation_level: string, message: string}>  $annotations
     * @return array{name: string, head_sha: string, status: string, conclusion?: string, output?: array{title: string, summary: string, annotations?: array<int, array{path: string, start_line: int, end_line: int, annotation_level: string, message: string}>}}
     */
    public function checkRun(
        string $name,
        string $headSha,
        string $status,
        ?string $conclusion,
        ?string $summary,
        array $annotations,
    ): array {
        $payload = [
            'name' => $name,
            'head_sha' => $headSha,
            'status' => $status,
        ];

        if ($status === 'completed' && $conclusion !== null) {
            $payload['conclusion'] = $conclusion;
        }

        if ($summary !== null) {
            $payload['output'] = [
                'title' => $name,
                'summary' => $summary,
            ];

            if ($annotations !== []) {
                $payload['output']['annotations'] = $annotations;
            }
        }

        return $payload;
    }
}
