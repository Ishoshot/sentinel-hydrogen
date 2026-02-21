<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Commands\CommandAgentService;
use App\Services\Commands\CommandInputClassificationService;
use App\Services\Commands\Contracts\CommandAgentServiceContract;
use App\Services\Commands\Contracts\CommandInputClassificationServiceContract;
use App\Services\Commands\Contracts\IssueContextServiceContract;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\IssueContextService;
use App\Services\Commands\PullRequestContextService;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use App\Services\Commands\Tools\FindSymbolTool;
use App\Services\Commands\Tools\GetFileStructureTool;
use App\Services\Commands\Tools\GetIssueCommentsTool;
use App\Services\Commands\Tools\GetIssueTimelineTool;
use App\Services\Commands\Tools\GetLinkedIssueReferencesTool;
use App\Services\Commands\Tools\ListFilesTool;
use App\Services\Commands\Tools\ReadFileTool;
use App\Services\Commands\Tools\SearchCodeTool;
use App\Services\Commands\Tools\SearchPatternTool;
use App\Services\Commands\Tools\SearchSimilarRunsOrFindingsTool;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Service provider for @sentinel command services.
 *
 * Registers the command agent and PR context services used for
 * interactive GitHub comment commands (@sentinel explain, etc.).
 */
final class CommandServiceProvider extends ServiceProvider
{
    /**
     * Register command services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(CommandAgentServiceContract::class, CommandAgentService::class);
        $this->app->bind(CommandInputClassificationServiceContract::class, CommandInputClassificationService::class);
        $this->app->bind(PullRequestContextServiceContract::class, PullRequestContextService::class);
        $this->app->bind(IssueContextServiceContract::class, IssueContextService::class);
        $this->app->scoped(IssueSnapshotResolver::class);

        $toolBuilders = [
            SearchCodeTool::class,
            SearchPatternTool::class,
            FindSymbolTool::class,
            ListFilesTool::class,
            ReadFileTool::class,
            GetFileStructureTool::class,
            GetIssueCommentsTool::class,
            GetIssueTimelineTool::class,
            GetLinkedIssueReferencesTool::class,
            SearchSimilarRunsOrFindingsTool::class,
        ];

        $this->app->tag($toolBuilders, 'command.tool-builders');

        $this->app
            ->when(CommandAgentService::class)
            ->needs('$toolBuilders')
            ->giveTagged('command.tool-builders');
    }
}
