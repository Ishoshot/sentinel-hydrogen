<?php

declare(strict_types=1);

use App\Services\Reviews\PrismReviewEngine;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

function buildTestContext(): array
{
    return [
        'run' => (object) ['id' => 1, 'workspace_id' => 1],
        'repository' => (object) ['id' => 1, 'full_name' => 'org/repo'],
        'policy_snapshot' => [],
        'pull_request' => [
            'number' => 1,
            'title' => 'Test PR',
            'body' => null,
            'base_branch' => 'main',
            'head_branch' => 'feature',
            'head_sha' => 'abc123',
            'sender_login' => 'user',
            'repository_full_name' => 'org/repo',
        ],
        'files' => [],
        'metrics' => ['files_changed' => 0, 'lines_added' => 0, 'lines_deleted' => 0],
    ];
}

function createFakeResponse(string $text, int $promptTokens = 50, int $completionTokens = 30): TextResponseFake
{
    return TextResponseFake::make()
        ->withText($text)
        ->withUsage(new Usage($promptTokens, $completionTokens));
}

it('parses a valid AI response and returns structured review data', function (): void {
    $context = buildTestContext();
    $context['policy_snapshot'] = [
        'severity_thresholds' => ['comment' => 'medium', 'block' => 'critical'],
        'enabled_rules' => ['security', 'maintainability'],
    ];
    $context['pull_request']['number'] = 42;
    $context['files'] = [
        ['filename' => 'src/Test.php', 'additions' => 10, 'deletions' => 2, 'changes' => 12],
    ];
    $context['metrics'] = ['files_changed' => 1, 'lines_added' => 10, 'lines_deleted' => 2];

    $jsonResponse = json_encode([
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
                'rationale' => 'Tests help ensure correctness.',
                'confidence' => 0.85,
                'file_path' => 'src/Test.php',
                'line_start' => 5,
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    Prism::fake([createFakeResponse($jsonResponse, 100, 50)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result)->toBeArray()
        ->and($result['summary']['overview'])->toBe('Review completed successfully.')
        ->and($result['summary']['risk_level'])->toBe('low')
        ->and($result['summary']['recommendations'])->toBe(['Consider adding tests.'])
        ->and($result['findings'])->toHaveCount(1)
        ->and($result['findings'][0]['severity'])->toBe('medium')
        ->and($result['findings'][0]['title'])->toBe('Missing tests')
        ->and($result['metrics']['files_changed'])->toBe(1)
        ->and($result['metrics']['tokens_used_estimated'])->toBe(150);
});

it('normalizes invalid severity to info', function (): void {
    $context = buildTestContext();

    $jsonResponse = json_encode([
        'summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [
            [
                'severity' => 'invalid_severity',
                'category' => 'security',
                'title' => 'Test finding',
                'description' => 'Test description',
                'rationale' => 'Test rationale',
                'confidence' => 0.5,
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    Prism::fake([createFakeResponse($jsonResponse)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result['findings'][0]['severity'])->toBe('info');
});

it('handles response wrapped in markdown code blocks', function (): void {
    $context = buildTestContext();

    $jsonContent = json_encode([
        'summary' => ['overview' => 'Review done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [],
    ], JSON_THROW_ON_ERROR);

    $wrappedResponse = "```json\n{$jsonContent}\n```";

    Prism::fake([createFakeResponse($wrappedResponse, 40, 20)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result['summary']['overview'])->toBe('Review done.')
        ->and($result['findings'])->toBeArray()
        ->and($result['findings'])->toBeEmpty();
});

it('throws exception for invalid JSON response', function (): void {
    $context = buildTestContext();

    Prism::fake([createFakeResponse('not valid json', 20, 10)]);

    $engine = app(PrismReviewEngine::class);
    $engine->review($context);
})->throws(RuntimeException::class, 'Failed to parse AI response as JSON');

it('normalizes empty summary with defaults', function (): void {
    $context = buildTestContext();

    $jsonResponse = json_encode([
        'summary' => [],
        'findings' => [],
    ], JSON_THROW_ON_ERROR);

    Prism::fake([createFakeResponse($jsonResponse, 30, 15)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result['summary']['overview'])->toBe('Review completed.')
        ->and($result['summary']['risk_level'])->toBe('low')
        ->and($result['summary']['recommendations'])->toBe([]);
});

it('normalizes invalid category to maintainability', function (): void {
    $context = buildTestContext();

    $jsonResponse = json_encode([
        'summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'unknown_category',
                'title' => 'Test finding',
                'description' => 'Test description',
                'rationale' => 'Test rationale',
                'confidence' => 0.5,
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    Prism::fake([createFakeResponse($jsonResponse)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result['findings'][0]['category'])->toBe('maintainability');
});

it('clamps confidence value between 0 and 1', function (): void {
    $context = buildTestContext();

    $jsonResponse = json_encode([
        'summary' => ['overview' => 'Done.', 'risk_level' => 'low', 'recommendations' => []],
        'findings' => [
            [
                'severity' => 'medium',
                'category' => 'security',
                'title' => 'High confidence finding',
                'description' => 'Test description',
                'rationale' => 'Test rationale',
                'confidence' => 1.5,
            ],
            [
                'severity' => 'low',
                'category' => 'style',
                'title' => 'Low confidence finding',
                'description' => 'Test description',
                'rationale' => 'Test rationale',
                'confidence' => -0.5,
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    Prism::fake([createFakeResponse($jsonResponse)]);

    $engine = app(PrismReviewEngine::class);
    $result = $engine->review($context);

    expect($result['findings'][0]['confidence'])->toBe(1.0)
        ->and($result['findings'][1]['confidence'])->toBe(0.0);
});
