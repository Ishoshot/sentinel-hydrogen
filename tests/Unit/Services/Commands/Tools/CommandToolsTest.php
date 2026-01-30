<?php

declare(strict_types=1);

use App\DataTransferObjects\SentinelConfig\PathsConfig;
use App\Models\CommandRun;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\CommandPathRules;
use App\Services\Commands\Tools\CodeIndexLookup;
use App\Services\Commands\Tools\FindSymbolTool;
use App\Services\Commands\Tools\GetFileStructureTool;
use App\Services\Commands\Tools\ListFilesTool;
use App\Services\Commands\Tools\ReadFileTool;
use App\Services\Commands\Tools\SearchCodeTool;
use App\Services\Commands\Tools\SearchPatternTool;
use App\Services\Commands\Tools\ToolResultFormatter;
use App\Services\Context\SensitiveDataRedactor;
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

    $formatter = new ToolResultFormatter();
    $lookup = new CodeIndexLookup();

    $tools = [
        (new SearchCodeTool($searchService, $formatter))->build($commandRun, $pathRules),
        (new SearchPatternTool())->build($commandRun, $pathRules),
        (new FindSymbolTool($searchService, $formatter))->build($commandRun, $pathRules),
        (new ListFilesTool())->build($commandRun, $pathRules),
        (new ReadFileTool($lookup))->build($commandRun, $pathRules),
        (new GetFileStructureTool($lookup))->build($commandRun, $pathRules),
    ];

    foreach ($tools as $tool) {
        expect($tool)->toBeInstanceOf(PrismTool::class);
    }
});
