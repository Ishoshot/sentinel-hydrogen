<?php

declare(strict_types=1);

namespace App\Services\Reviews\Contracts;

use App\Models\Repository;
use App\Models\Run;

interface PullRequestDataResolver
{
    /**
     * @return array{pull_request: array{number: int, title: string, body: string|null, base_branch: string, head_branch: string, head_sha: string, sender_login: string, repository_full_name: string}, files: array<int, array{filename: string, additions: int, deletions: int, changes: int}>, metrics: array{files_changed: int, lines_added: int, lines_deleted: int}}
     */
    public function resolve(Repository $repository, Run $run): array;
}
