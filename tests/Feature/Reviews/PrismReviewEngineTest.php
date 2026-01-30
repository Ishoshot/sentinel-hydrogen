<?php

declare(strict_types=1);

use App\Enums\AI\AiProvider;
use App\Enums\Auth\ProviderType;
use App\Models\Connection;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\ProviderKey;
use App\Models\Repository;
use App\Services\Context\ContextBag;
use App\Services\Reviews\PrismReviewEngine;
use App\Services\Reviews\ValueObjects\ReviewResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake([
        '*' => Http::response(['input_tokens' => 100], 200),
    ]);
});

function createRepositoryWithByokKey(): Repository
{
    // Use firstOrCreate pattern to avoid unique constraint violations
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    ProviderKey::factory()->create([
        'repository_id' => $repository->id,
        'workspace_id' => $repository->workspace_id,
        'provider' => AiProvider::Anthropic,
        'encrypted_key' => 'sk-ant-api03-test-key-for-testing',
    ]);

    return $repository;
}

function buildTestContext(?Repository $repository = null): array
{
    $repository ??= createRepositoryWithByokKey();

    return [
        'repository' => $repository,
        'policy_snapshot' => [],
        'context_bag' => new ContextBag(
            pullRequest: [
                'number' => 1,
                'title' => 'Test PR',
                'body' => null,
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'head_sha' => 'abc123',
                'sender_login' => 'user',
                'repository_full_name' => 'org/repo',
            ],
            files: [],
            metrics: ['files_changed' => 0, 'lines_added' => 0, 'lines_deleted' => 0],
        ),
    ];
}

/**
 * Create a fake structured response from an array.
 *
 * @param  array<string, mixed>  $structured
 */
function createFakeStructuredResponse(array $structured, int $promptTokens = 50, int $completionTokens = 30): \Prism\Prism\Testing\StructuredResponseFake
{
    return \Prism\Prism\Testing\StructuredResponseFake::make()
        ->withStructured($structured)
        ->withUsage(new Usage($promptTokens, $completionTokens));
}

it('parses a valid AI response and returns structured review data', function (): void {
    $repository = createRepositoryWithByokKey();

    $context = [
        'repository' => $repository,
        'policy_snapshot' => [
            'severity_thresholds' => ['comment' => 'medium', 'block' => 'critical'],
            'enabled_rules' => ['security', 'maintainability'],
        ],
        'context_bag' => new ContextBag(
            pullRequest: [
                'number' => 42,
                'title' => 'Test PR',
                'body' => null,
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'head_sha' => 'abc123',
                'sender_login' => 'user',
                'repository_full_name' => 'org/repo',
            ],
            files: [
                ['filename' => 'src/Test.php', 'status' => 'modified', 'additions' => 10, 'deletions' => 2, 'changes' => 12, 'patch' => null],
            ],
            metrics: ['files_changed' => 1, 'lines_added' => 10, 'lines_deleted' => 2],
        ),
    ];

    $responseData = [
        'summary' => [
            'overview' => 'Review completed successfully.',
            'risk_level' => 'low',
            'recommendations' => ['Consider adding tests.'],
        ],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'maintainability',
                'title' => 'Missing tests',
                'description' => 'This code lacks test coverage.',
                'impact' => 'Tests help ensure correctness.',
                'confidence' => 0.85,
                'file_path' => 'src/Test.php',
                'line_start' => 5,
            ],
        ],
    ];

    Prism::fake([createFakeStructuredResponse($responseData, 100, 50)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result)->toBeInstanceOf(ReviewResult::class)
        ->and($result->summary->overview)->toBe('Review completed successfully.')
        ->and($result->summary->riskLevel->value)->toBe('low')
        ->and($result->summary->recommendations)->toBe(['Consider adding tests.'])
        ->and($result->findings)->toHaveCount(1)
        ->and($result->findings[0]->severity->value)->toBe('medium')
        ->and($result->findings[0]->title)->toBe('Missing tests')
        ->and($result->metrics->filesChanged)->toBe(1)
        ->and($result->metrics->tokensUsedEstimated)->toBe(150);
});

it('normalizes invalid severity to info', function (): void {
    $context = buildTestContext();

    $responseData = [
        'summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [
            [
                'severity' => 'invalid_severity',
                'category' => 'security',
                'title' => 'Test finding',
                'description' => 'Test description',
                'impact' => 'Test rationale',
                'confidence' => 0.5,
            ],
        ],
    ];

    Prism::fake([createFakeStructuredResponse($responseData)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result->findings[0]->severity->value)->toBe('info');
});

it('handles structured response and returns correct data', function (): void {
    $context = buildTestContext();

    $responseData = [
        'summary' => ['overview' => 'Review done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [],
    ];

    Prism::fake([createFakeStructuredResponse($responseData, 40, 20)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result->summary->overview)->toBe('Review done.')
        ->and($result->findings)->toBeArray()
        ->and($result->findings)->toBeEmpty();
});

it('handles empty findings array gracefully', function (): void {
    $context = buildTestContext();

    $responseData = [
        'summary' => ['overview' => 'No issues found.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [],
    ];

    Prism::fake([createFakeStructuredResponse($responseData, 20, 10)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result->findings)->toBeEmpty();
});

it('normalizes empty summary with defaults', function (): void {
    $context = buildTestContext();

    $responseData = [
        'summary' => [],
        'findings' => [],
    ];

    Prism::fake([createFakeStructuredResponse($responseData, 30, 15)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result->summary->overview)->toBe('Review completed.')
        ->and($result->summary->riskLevel->value)->toBe('low')
        ->and($result->summary->recommendations)->toBe([]);
});

it('normalizes invalid category to maintainability', function (): void {
    $context = buildTestContext();

    $responseData = [
        'summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'unknown_category',
                'title' => 'Test finding',
                'description' => 'Test description',
                'impact' => 'Test rationale',
                'confidence' => 0.5,
            ],
        ],
    ];

    Prism::fake([createFakeStructuredResponse($responseData)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result->findings[0]->category->value)->toBe('maintainability');
});

it('clamps confidence value between 0 and 1', function (): void {
    $context = buildTestContext();

    $responseData = [
        'summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'security',
                'title' => 'High confidence finding',
                'description' => 'Test description',
                'impact' => 'Test rationale',
                'confidence' => 1.5,
            ],
            [
                'severity' => 'low',
                'category' => 'style',
                'title' => 'Low confidence finding',
                'description' => 'Test description',
                'impact' => 'Test rationale',
                'confidence' => -0.5,
            ],
        ],
    ];

    Prism::fake([createFakeStructuredResponse($responseData)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result->findings[0]->confidence)->toBe(1.0)
        ->and($result->findings[1]->confidence)->toBe(0.0);
});

it('throws NoProviderKeyException when repository has no BYOK keys', function (): void {
    // Create repository WITHOUT provider keys
    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->active()->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();

    $context = [
        'repository' => $repository,
        'policy_snapshot' => [],
        'context_bag' => new ContextBag(
            pullRequest: [
                'number' => 1,
                'title' => 'Test PR',
                'body' => null,
                'base_branch' => 'main',
                'head_branch' => 'feature',
                'head_sha' => 'abc123',
                'sender_login' => 'user',
                'repository_full_name' => 'org/repo',
            ],
            files: [],
            metrics: ['files_changed' => 0, 'lines_added' => 0, 'lines_deleted' => 0],
        ),
    ];

    $engine = app(PrismReviewEngine::class);
    $engine->review($context);
})->throws(App\Exceptions\NoProviderKeyException::class, 'No provider keys configured for this repository');
