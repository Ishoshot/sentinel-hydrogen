<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\RunStatus;
use App\Models\Repository;
use App\Models\Run;

final readonly class CreatePullRequestRun
{
    /**
     * @param  array{action: string, installation_id: int, repository_id: int, repository_full_name: string, pull_request_number: int, pull_request_title: string, pull_request_body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string}  $payload
     */
    public function handle(Repository $repository, array $payload): Run
    {
        $externalReference = sprintf(
            'github:pull_request:%s:%s',
            $payload['pull_request_number'],
            $payload['head_sha']
        );

        return Run::query()->firstOrCreate(
            [
                'workspace_id' => $repository->workspace_id,
                'repository_id' => $repository->id,
                'external_reference' => $externalReference,
            ],
            [
                'status' => RunStatus::Queued,
                'started_at' => now(),
                'metadata' => [
                    'provider' => 'github',
                    'repository_full_name' => $payload['repository_full_name'],
                    'pull_request_number' => $payload['pull_request_number'],
                    'pull_request_title' => $payload['pull_request_title'],
                    'pull_request_body' => $payload['pull_request_body'],
                    'base_branch' => $payload['base_branch'],
                    'head_branch' => $payload['head_branch'],
                    'head_sha' => $payload['head_sha'],
                    'sender_login' => $payload['sender_login'],
                    'action' => $payload['action'],
                    'installation_id' => $payload['installation_id'],
                ],
                'created_at' => now(),
            ]
        );
    }
}
