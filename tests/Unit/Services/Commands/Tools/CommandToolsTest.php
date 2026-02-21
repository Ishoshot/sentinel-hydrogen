<?php

declare(strict_types=1);

use App\Models\CommandRun;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Resolvers\IssueApiParameterResolver;
use App\Services\Commands\Resolvers\IssueLinkedReferencesResolver;
use App\Services\Commands\Resolvers\IssueSnapshotResolver;
use App\Services\Commands\Resolvers\SimilarRunsAndFindingsResolver;
use App\Services\Commands\Tools\CodeIndexLookup;
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
use App\Services\Commands\Tools\ToolResultFormatter;
use App\Services\Context\SensitiveDataRedactor;
use App\Services\GitHub\Contracts\GitHubApiServiceContract;
use App\Services\SentinelConfig\ValueObjects\PathsConfig;
use App\Support\PathRuleMatcher;
use Prism\Prism\Tool as PrismTool;

it('builds command tools', function (): void {
    $repository = new Repository();
    $repository->id = 1;
    $repository->owner = 'acme';
    $repository->name = 'sentinel';
    $repository->full_name = 'acme/sentinel';

    $commandRun = new CommandRun();
    $commandRun->repository_id = $repository->id;
    $commandRun->setRelation('repository', $repository);

    $pathRules = new CommandPathRules(PathsConfig::default(), new SensitiveDataRedactor(), new PathRuleMatcher());
    $searchService = Mockery::mock(CodeSearchServiceContract::class);
    $gitHubApiService = Mockery::mock(GitHubApiServiceContract::class);

    $formatter = new ToolResultFormatter();
    $lookup = new CodeIndexLookup();
    $snapshotResolver = new IssueSnapshotResolver($gitHubApiService, new IssueApiParameterResolver());

    $tools = [
        (new SearchCodeTool($searchService, $formatter))->build($commandRun, $pathRules),
        (new SearchPatternTool())->build($commandRun, $pathRules),
        (new FindSymbolTool($searchService, $formatter))->build($commandRun, $pathRules),
        (new ListFilesTool())->build($commandRun, $pathRules),
        (new ReadFileTool($lookup))->build($commandRun, $pathRules),
        (new GetFileStructureTool($lookup))->build($commandRun, $pathRules),
        (new GetIssueCommentsTool($snapshotResolver, $formatter))->build($commandRun, $pathRules),
        (new GetIssueTimelineTool($snapshotResolver, $formatter))->build($commandRun, $pathRules),
        (new GetLinkedIssueReferencesTool($snapshotResolver, new IssueLinkedReferencesResolver()))->build($commandRun, $pathRules),
        (new SearchSimilarRunsOrFindingsTool(new SimilarRunsAndFindingsResolver()))->build($commandRun, $pathRules),
    ];

    foreach ($tools as $tool) {
        expect($tool)->toBeInstanceOf(PrismTool::class);
    }
});
