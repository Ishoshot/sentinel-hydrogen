<?php

declare(strict_types=1);

namespace App\Services\Commands\Resolvers;

final readonly class IssueLinkedReferencesResolver
{
    /**
     * Resolve linked PRs and commits from issue body/comments/timeline.
     *
     * @param  array<string, mixed>  $issue
     * @param  array<int, array<string, mixed>>  $comments
     * @param  array<int, array<string, mixed>>  $timeline
     * @return array{
     *     pull_requests: array<int, array{number: int, title: string, state: string, source: string}>,
     *     commits: array<int, array{sha: string, message: string, source: string}>
     * }
     */
    public function resolve(array $issue, array $comments, array $timeline): array
    {
        $pullRequests = [];
        $commits = [];

        $this->collectFromText((string) ($issue['body'] ?? ''), 'issue_body', $pullRequests, $commits);

        foreach ($comments as $comment) {
            $this->collectFromText((string) ($comment['body'] ?? ''), 'issue_comment', $pullRequests, $commits);
        }

        foreach ($timeline as $event) {
            $this->collectFromTimelineEvent($event, $pullRequests, $commits);
        }

        return [
            'pull_requests' => array_values($pullRequests),
            'commits' => array_values($commits),
        ];
    }

    /**
     * @param  array<string, array{number: int, title: string, state: string, source: string}>  $pullRequests
     * @param  array<string, array{sha: string, message: string, source: string}>  $commits
     */
    private function collectFromText(string $text, string $source, array &$pullRequests, array &$commits): void
    {
        if ($text === '') {
            return;
        }

        preg_match_all('~https?://github\.com/[^/\s]+/[^/\s]+/pull/(\d+)~i', $text, $pullMatches);
        foreach ($pullMatches[1] as $pullNumber) {
            $number = (int) $pullNumber;
            $key = 'pr:'.$number;

            if (! isset($pullRequests[$key])) {
                $pullRequests[$key] = [
                    'number' => $number,
                    'title' => '',
                    'state' => '',
                    'source' => $source,
                ];
            }
        }

        preg_match_all('~https?://github\.com/[^/\s]+/[^/\s]+/commit/([a-f0-9]{7,40})~i', $text, $commitMatches);
        foreach ($commitMatches[1] as $sha) {
            $normalized = mb_strtolower($sha);
            $key = 'commit:'.$normalized;

            if (! isset($commits[$key])) {
                $commits[$key] = [
                    'sha' => $normalized,
                    'message' => '',
                    'source' => $source,
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, array{number: int, title: string, state: string, source: string}>  $pullRequests
     * @param  array<string, array{sha: string, message: string, source: string}>  $commits
     */
    private function collectFromTimelineEvent(array $event, array &$pullRequests, array &$commits): void
    {
        $eventType = is_string($event['event'] ?? null) ? $event['event'] : 'timeline';

        $sourceIssue = is_array($event['source']['issue'] ?? null)
            ? $event['source']['issue']
            : null;

        if ($sourceIssue !== null && isset($sourceIssue['pull_request']) && is_int($sourceIssue['number'] ?? null)) {
            $number = $sourceIssue['number'];
            $key = 'pr:'.$number;

            if (! isset($pullRequests[$key])) {
                $pullRequests[$key] = [
                    'number' => $number,
                    'title' => is_string($sourceIssue['title'] ?? null) ? $sourceIssue['title'] : '',
                    'state' => is_string($sourceIssue['state'] ?? null) ? $sourceIssue['state'] : '',
                    'source' => 'timeline:'.$eventType,
                ];
            }
        }

        $commitSha = is_string($event['commit_id'] ?? null) ? mb_strtolower($event['commit_id']) : null;
        if ($commitSha !== null && preg_match('/^[a-f0-9]{7,40}$/', $commitSha) === 1) {
            $key = 'commit:'.$commitSha;

            if (! isset($commits[$key])) {
                $commits[$key] = [
                    'sha' => $commitSha,
                    'message' => is_string($event['commit_url'] ?? null) ? $event['commit_url'] : '',
                    'source' => 'timeline:'.$eventType,
                ];
            }
        }

        $body = is_string($event['body'] ?? null) ? $event['body'] : '';
        $this->collectFromText($body, 'timeline:'.$eventType, $pullRequests, $commits);
    }
}
