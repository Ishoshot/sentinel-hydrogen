<?php

declare(strict_types=1);

use App\Actions\SentinelConfig\Contracts\FetchesSentinelConfig;
use App\Enums\AI\AiProvider;
use App\Enums\Commands\CommandType;
use App\Models\AiOption;
use App\Models\CommandRun;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\CodeIndexing\Contracts\CodeSearchServiceContract;
use App\Services\Commands\Builders\CommandAgentExecutionContextBuilder;
use App\Services\Commands\Builders\CommandPromptBuilder;
use App\Services\Commands\Builders\IssueRetrievalContextBuilder;
use App\Services\Commands\CommandPathRulesResolver;
use App\Services\Commands\Contracts\IssueContextServiceContract;
use App\Services\Commands\Contracts\PullRequestContextServiceContract;
use App\Services\Commands\Normalizers\CommandContextHintsNormalizer;
use App\Services\Commands\Resolvers\CommandAgentProviderResolver;
use App\Services\Context\SensitiveDataRedactor;
use App\Services\Reviews\Contracts\ProviderKeyResolver;
use App\Services\SentinelConfig\ParseSentinelConfig;
use App\Support\PathRuleMatcher;

it('fails open when issue retrieval context building throws', function (): void {
    $repository = new Repository([
        'id' => 1,
        'full_name' => 'owner/repo',
        'name' => 'repo',
    ]);
    $repository->setRelation('settings', null);

    $commandRun = new CommandRun([
        'id' => 10,
        'query' => 'brainstorm queue timeout fix',
        'command_type' => CommandType::Explain,
        'issue_number' => 44,
        'is_pull_request' => false,
        'context_snapshot' => ['context_hints' => []],
        'metadata' => [],
    ]);
    $commandRun->setRelation('repository', $repository);

    $providerKey = new ProviderKey();
    $providerKey->provider = AiProvider::Anthropic;
    $providerKey->encrypted_key = 'test-key';
    $providerKey->setRelation('providerModel', new AiOption([
        'identifier' => 'claude-sonnet-4-5',
    ]));

    $keyResolver = Mockery::mock(ProviderKeyResolver::class);
    $keyResolver->shouldReceive('getProviderKey')
        ->once()
        ->andReturn($providerKey);

    $providerResolver = new CommandAgentProviderResolver($keyResolver);

    $prContextService = Mockery::mock(PullRequestContextServiceContract::class);
    $prContextService->shouldReceive('buildContext')->once()->andReturn(null);
    $prContextService->shouldReceive('getMetadata')->once()->andReturn(null);

    $issueContextService = Mockery::mock(IssueContextServiceContract::class);
    $issueContextService->shouldReceive('buildContext')->once()->andReturn("## Issue Context\n\n**Title**: Queue timeout");

    $searchService = Mockery::mock(CodeSearchServiceContract::class);
    $searchService->shouldReceive('search')->once()->andThrow(new RuntimeException('retrieval unavailable'));
    $issueRetrievalContextBuilder = new IssueRetrievalContextBuilder($searchService);

    $fetchConfig = Mockery::mock(FetchesSentinelConfig::class);
    $fetchConfig->shouldNotReceive('handle');

    $pathRulesResolver = new CommandPathRulesResolver(
        fetchConfig: $fetchConfig,
        configParser: app(ParseSentinelConfig::class),
        redactor: new SensitiveDataRedactor(),
        matcher: new PathRuleMatcher(),
    );

    $builder = new CommandAgentExecutionContextBuilder(
        providerResolver: $providerResolver,
        contextHintsNormalizer: new CommandContextHintsNormalizer(),
        prContextService: $prContextService,
        issueContextService: $issueContextService,
        issueRetrievalContextBuilder: $issueRetrievalContextBuilder,
        promptBuilder: app(CommandPromptBuilder::class),
        pathRulesResolver: $pathRulesResolver,
    );

    $executionContext = $builder->prepare($commandRun, []);

    expect($executionContext->systemPrompt)->toContain('Security Boundaries')
        ->and($executionContext->userMessage)->toContain('<<<UNTRUSTED_CONTEXT_START:issue>>>')
        ->and($executionContext->userMessage)->not->toContain('<<<UNTRUSTED_CONTEXT_START:issue_retrieval>>>')
        ->and($executionContext->model)->toBe('claude-sonnet-4-5');
});
