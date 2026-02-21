<?php

declare(strict_types=1);

namespace App\Services\Commands;

use App\Models\CommandRun;
use App\Services\Commands\Contracts\IssueContextServiceContract;
use App\Services\Commands\Formatters\IssueContextFormatter;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class IssueContextService implements IssueContextServiceContract
{
    /**
     * Create a new IssueContextService instance.
     */
    public function __construct(
        private IssueSnapshotResolver $issueSnapshotResolver,
        private IssueContextFormatter $issueContextFormatter,
    ) {}

    /**
     * Build context string from issue data.
     */
    public function buildContext(CommandRun $commandRun): ?string
    {
        try {
            $snapshot = $this->issueSnapshotResolver->resolve($commandRun);
            if (! $snapshot instanceof ValueObjects\IssueSnapshot) {
                Log::debug('Issue context unavailable for command run', [
                    'command_run_id' => $commandRun->id,
                    'is_pull_request' => $commandRun->is_pull_request,
                    'issue_number' => $commandRun->issue_number,
                    'repository' => $commandRun->repository?->full_name,
                ]);

                return null;
            }

            if (isset($snapshot->issue['pull_request'])) {
                Log::debug('Issue context skipped because target is a pull request', [
                    'command_run_id' => $commandRun->id,
                    'issue_number' => $commandRun->issue_number,
                    'repository' => $commandRun->repository?->full_name,
                ]);

                return null;
            }

            $context = $this->issueContextFormatter->format($snapshot->issue, $snapshot->comments);

            Log::debug('Issue context prepared for command run', [
                'command_run_id' => $commandRun->id,
                'issue_number' => $commandRun->issue_number,
                'repository' => $commandRun->repository?->full_name,
                'comments_count' => count($snapshot->comments),
                'context_chars' => mb_strlen($context),
            ]);

            return $context;
        } catch (Throwable $throwable) {
            Log::warning('Failed to fetch issue context for command', [
                'command_run_id' => $commandRun->id,
                'repository' => $commandRun->repository?->full_name,
                'issue_number' => $commandRun->issue_number,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }
}
