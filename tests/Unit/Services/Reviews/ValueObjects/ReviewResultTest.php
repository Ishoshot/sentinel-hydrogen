<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Services\Reviews\ValueObjects\PromptSnapshot;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;
use App\Services\Reviews\ValueObjects\ReviewSummary;

it('can be constructed with all parameters', function (): void {
    $summary = new ReviewSummary(
        overview: 'Good code',
        verdict: ReviewVerdict::Approve,
        riskLevel: RiskLevel::Low,
    );
    $finding = new ReviewFinding(
        severity: SentinelConfigSeverity::Medium,
        category: FindingCategory::Maintainability,
        title: 'Test',
        description: 'Description',
        impact: 'Minor',
        confidence: 0.8,
    );
    $metrics = new ReviewMetrics(
        filesChanged: 1,
        linesAdded: 10,
        linesDeleted: 5,
        inputTokens: 1000,
        outputTokens: 500,
        tokensUsedEstimated: 1500,
        model: 'claude-3',
        provider: 'anthropic',
        durationMs: 2000,
    );
    $snapshot = new PromptSnapshot(
        systemVersion: '1.0',
        systemHash: 'abc',
        userVersion: '1.0',
        userHash: 'def',
        hashAlgorithm: 'sha256',
    );

    $result = new ReviewResult(
        summary: $summary,
        findings: [$finding],
        metrics: $metrics,
        promptSnapshot: $snapshot,
    );

    expect($result->summary)->toBe($summary);
    expect($result->findings)->toBe([$finding]);
    expect($result->metrics)->toBe($metrics);
    expect($result->promptSnapshot)->toBe($snapshot);
});

it('can be constructed without prompt snapshot', function (): void {
    $summary = new ReviewSummary(
        overview: 'Overview',
        verdict: ReviewVerdict::Comment,
        riskLevel: RiskLevel::Medium,
    );
    $metrics = new ReviewMetrics(
        filesChanged: 2,
        linesAdded: 20,
        linesDeleted: 10,
        inputTokens: 500,
        outputTokens: 200,
        tokensUsedEstimated: 700,
        model: 'gpt-4',
        provider: 'openai',
        durationMs: 1500,
    );

    $result = new ReviewResult(
        summary: $summary,
        findings: [],
        metrics: $metrics,
    );

    expect($result->promptSnapshot)->toBeNull();
});

it('creates from array with all fields', function (): void {
    $result = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Great PR',
            'verdict' => 'approve',
            'risk_level' => 'low',
            'strengths' => ['Clean code'],
            'concerns' => [],
            'recommendations' => ['Add tests'],
        ],
        'findings' => [
            [
                'severity' => 'high',
                'category' => 'security',
                'title' => 'SQL Injection',
                'description' => 'Vulnerable query',
                'impact' => 'Data breach',
                'confidence' => 0.95,
            ],
        ],
        'metrics' => [
            'files_changed' => 3,
            'lines_added' => 100,
            'lines_deleted' => 50,
            'input_tokens' => 2000,
            'output_tokens' => 1000,
            'tokens_used_estimated' => 3000,
            'model' => 'claude-3-sonnet',
            'provider' => 'anthropic',
            'duration_ms' => 3000,
        ],
        'prompt_snapshot' => [
            'system' => ['version' => '2.0', 'hash' => 'sys123'],
            'user' => ['version' => '2.0', 'hash' => 'usr456'],
            'hash_algorithm' => 'sha256',
        ],
    ]);

    expect($result->summary->overview)->toBe('Great PR');
    expect($result->summary->verdict)->toBe(ReviewVerdict::Approve);
    expect($result->findings)->toHaveCount(1);
    expect($result->findings[0]->title)->toBe('SQL Injection');
    expect($result->metrics->filesChanged)->toBe(3);
    expect($result->promptSnapshot)->not->toBeNull();
    expect($result->promptSnapshot->hashAlgorithm)->toBe('sha256');
});

it('creates from array without prompt snapshot', function (): void {
    $result = ReviewResult::fromArray([
        'summary' => [
            'overview' => 'Overview',
            'verdict' => 'comment',
            'risk_level' => 'medium',
        ],
        'findings' => [],
        'metrics' => [
            'files_changed' => 1,
            'lines_added' => 5,
            'lines_deleted' => 2,
            'input_tokens' => 100,
            'output_tokens' => 50,
            'tokens_used_estimated' => 150,
            'model' => 'test',
            'provider' => 'test',
            'duration_ms' => 100,
        ],
    ]);

    expect($result->promptSnapshot)->toBeNull();
});

it('counts findings', function (): void {
    $summary = new ReviewSummary(
        overview: 'Test',
        verdict: ReviewVerdict::RequestChanges,
        riskLevel: RiskLevel::High,
    );
    $metrics = new ReviewMetrics(1, 1, 1, 1, 1, 1, 'test', 'test', 100);
    $findings = [
        new ReviewFinding(SentinelConfigSeverity::High, FindingCategory::Security, 'F1', 'D1', 'I1', 0.9),
        new ReviewFinding(SentinelConfigSeverity::Medium, FindingCategory::Correctness, 'F2', 'D2', 'I2', 0.8),
        new ReviewFinding(SentinelConfigSeverity::Low, FindingCategory::Style, 'F3', 'D3', 'I3', 0.7),
    ];

    $result = new ReviewResult($summary, $findings, $metrics);

    expect($result->findingCount())->toBe(3);
});

it('checks if it has findings when present', function (): void {
    $summary = new ReviewSummary('Test', ReviewVerdict::Comment, RiskLevel::Low);
    $metrics = new ReviewMetrics(1, 1, 1, 1, 1, 1, 'test', 'test', 100);
    $finding = new ReviewFinding(SentinelConfigSeverity::Info, FindingCategory::Documentation, 'F', 'D', 'I', 0.5);

    $result = new ReviewResult($summary, [$finding], $metrics);

    expect($result->hasFindings())->toBeTrue();
});

it('checks if it has no findings when empty', function (): void {
    $summary = new ReviewSummary('Test', ReviewVerdict::Approve, RiskLevel::Low);
    $metrics = new ReviewMetrics(1, 1, 1, 1, 1, 1, 'test', 'test', 100);

    $result = new ReviewResult($summary, [], $metrics);

    expect($result->hasFindings())->toBeFalse();
});

it('converts to array with prompt snapshot', function (): void {
    $summary = new ReviewSummary('Overview', ReviewVerdict::Approve, RiskLevel::Low);
    $metrics = new ReviewMetrics(1, 10, 5, 100, 50, 150, 'model', 'provider', 1000);
    $finding = new ReviewFinding(SentinelConfigSeverity::Medium, FindingCategory::Performance, 'Title', 'Desc', 'Impact', 0.8);
    $snapshot = new PromptSnapshot(
        systemVersion: '1.0',
        systemHash: 'sys',
        userVersion: '1.0',
        userHash: 'usr',
        hashAlgorithm: 'sha256',
    );

    $result = new ReviewResult($summary, [$finding], $metrics, $snapshot);
    $array = $result->toArray();

    expect($array)->toHaveKey('summary');
    expect($array)->toHaveKey('findings');
    expect($array)->toHaveKey('metrics');
    expect($array)->toHaveKey('prompt_snapshot');
    expect($array['findings'])->toHaveCount(1);
});

it('converts to array without prompt snapshot', function (): void {
    $summary = new ReviewSummary('Overview', ReviewVerdict::Comment, RiskLevel::Medium);
    $metrics = new ReviewMetrics(1, 10, 5, 100, 50, 150, 'model', 'provider', 1000);

    $result = new ReviewResult($summary, [], $metrics);
    $array = $result->toArray();

    expect($array)->toHaveKey('summary');
    expect($array)->toHaveKey('findings');
    expect($array)->toHaveKey('metrics');
    expect($array)->not->toHaveKey('prompt_snapshot');
});
