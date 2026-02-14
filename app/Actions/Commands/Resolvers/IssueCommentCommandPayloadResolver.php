<?php

declare(strict_types=1);

namespace App\Actions\Commands\Resolvers;

final class IssueCommentCommandPayloadResolver
{
    /**
     * Resolve normalized command payload data from an issue_comment webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     action: mixed,
     *     comment_body: mixed,
     *     comment_id: mixed,
     *     sender_login: mixed,
     *     repository_full_name: mixed,
     *     installation_id: mixed,
     *     issue_number: mixed,
     *     is_pull_request: bool,
     *     context: array{
     *         installation_id: mixed,
     *         repository: mixed,
     *         sender: mixed,
     *         comment_id: mixed,
     *         issue_number: mixed,
     *         is_pull_request: bool
     *     }
     * }
     */
    public function resolve(array $payload): array
    {
        $action = $payload['action'] ?? '';
        $commentBody = $payload['comment']['body'] ?? '';
        $commentId = $payload['comment']['id'] ?? 0;
        $senderLogin = $payload['sender']['login'] ?? '';
        $repositoryFullName = $payload['repository']['full_name'] ?? '';
        $installationId = $payload['installation']['id'] ?? 0;
        $issueNumber = $payload['issue']['number'] ?? null;
        $isPullRequest = isset($payload['issue']['pull_request']);

        return [
            'action' => $action,
            'comment_body' => $commentBody,
            'comment_id' => $commentId,
            'sender_login' => $senderLogin,
            'repository_full_name' => $repositoryFullName,
            'installation_id' => $installationId,
            'issue_number' => $issueNumber,
            'is_pull_request' => $isPullRequest,
            'context' => [
                'installation_id' => $installationId,
                'repository' => $repositoryFullName,
                'sender' => $senderLogin,
                'comment_id' => $commentId,
                'issue_number' => $issueNumber,
                'is_pull_request' => $isPullRequest,
            ],
        ];
    }
}
