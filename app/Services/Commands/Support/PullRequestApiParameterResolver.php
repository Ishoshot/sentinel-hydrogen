<?php

declare(strict_types=1);

namespace App\Services\Commands\Support;

use App\Models\CommandRun;

final readonly class PullRequestApiParameterResolver
{
    /**
     * @return array{0: int, 1: string, 2: string, 3: int}|null
     */
    public function resolve(CommandRun $commandRun): ?array
    {
        if (! $commandRun->is_pull_request || $commandRun->issue_number === null) {
            return null;
        }

        $repository = $commandRun->repository;
        if ($repository === null) {
            return null;
        }

        $installation = $repository->installation;
        if ($installation === null) {
            return null;
        }

        $fullName = $repository->full_name ?? '';
        if ($fullName === '' || ! str_contains((string) $fullName, '/')) {
            return null;
        }

        [$owner, $repo] = explode('/', (string) $fullName, 2);

        return [$installation->installation_id, $owner, $repo, $commandRun->issue_number];
    }
}
