<?php

declare(strict_types=1);

use App\Actions\Reviews\Loggers\ReviewRunActivityLogger;
use App\Enums\Reviews\ReviewVerdict;
use App\Enums\Reviews\RiskLevel;
use App\Enums\Workspace\ActivityType;
use App\Exceptions\NoProviderKeyException;
use App\Models\Activity;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use App\Services\Reviews\ValueObjects\ReviewFinding;
use App\Services\Reviews\ValueObjects\ReviewMetrics;
use App\Services\Reviews\ValueObjects\ReviewResult;
use App\Services\Reviews\ValueObjects\ReviewSummary;

function makeTestReviewResult(RiskLevel $riskLevel = RiskLevel::Low): ReviewResult
{
    return new ReviewResult(
        summary: new ReviewSummary(
            overview: 'Test review overview',
            verdict: ReviewVerdict::Comment,
            riskLevel: $riskLevel,
        ),
        findings: [],
        metrics: new ReviewMetrics(
            filesChanged: 5,
            linesAdded: 100,
            linesDeleted: 20,
            inputTokens: 1000,
            outputTokens: 500,
            tokensUsedEstimated: 1500,
            model: 'claude-3-5-sonnet',
            provider: 'anthropic',
            durationMs: 3000,
        ),
    );
}

it('logs completed review run activity', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 42,
            'repository_full_name' => 'acme/repo',
        ],
    ]);

    $result = makeTestReviewResult(RiskLevel::Medium);

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logCompleted($run, $result, []);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::RunCompleted->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Review completed for PR #42 in acme/repo')
        ->and($activity->metadata['findings_count'])->toBe(0)
        ->and($activity->metadata['risk_level'])->toBe('medium')
        ->and($activity->metadata['pull_request_number'])->toBe(42);
});

it('logs completed review with findings count', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 10,
            'repository_full_name' => 'org/project',
        ],
    ]);

    $result = makeTestReviewResult();

    $filteredFindings = [
        new ReviewFinding(
            severity: App\Enums\SentinelConfig\SentinelConfigSeverity::High,
            category: App\Enums\Reviews\FindingCategory::Security,
            title: 'SQL Injection',
            description: 'Possible SQL injection',
            impact: 'High',
            confidence: 0.95,
        ),
        new ReviewFinding(
            severity: App\Enums\SentinelConfig\SentinelConfigSeverity::Medium,
            category: App\Enums\Reviews\FindingCategory::Performance,
            title: 'N+1 query',
            description: 'N+1 query detected',
            impact: 'Medium',
            confidence: 0.8,
        ),
    ];

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logCompleted($run, $result, $filteredFindings);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::RunCompleted->value)
        ->first();

    expect($activity->metadata['findings_count'])->toBe(2);
});

it('logs failed review run activity', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 55,
            'repository_full_name' => 'acme/widget',
        ],
    ]);

    $exception = new RuntimeException('Provider API returned 500');

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logFailed($run, $exception);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::RunFailed->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Review failed for PR #55 in acme/widget')
        ->and($activity->metadata['error_type'])->toBe(RuntimeException::class)
        ->and($activity->metadata['error_message'])->toBe('Provider API returned 500')
        ->and($activity->metadata['pull_request_number'])->toBe(55);
});

it('logs skipped no provider keys activity', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 33,
            'repository_full_name' => 'acme/app',
        ],
    ]);

    $exception = NoProviderKeyException::noProvidersConfigured();

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logSkippedNoProviderKeys($run, $exception);

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::RunSkipped->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Review skipped for PR #33 in acme/app - no provider keys configured')
        ->and($activity->metadata['skip_reason'])->toBe('no_provider_keys')
        ->and($activity->metadata['skip_message'])->toBe('No provider keys configured for this repository')
        ->and($activity->metadata['pull_request_number'])->toBe(33);
});

it('does not log activity when run has no workspace', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $run->setRelation('workspace', null);

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logFailed($run, new RuntimeException('Failure'));

    expect(Activity::count())->toBe(0);
});

it('uses default values when metadata lacks pull request info', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => null,
    ]);

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logFailed($run, new RuntimeException('Error'));

    $activity = Activity::where('workspace_id', $workspace->id)
        ->where('type', ActivityType::RunFailed->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Review failed for PR #0 in unknown')
        ->and($activity->metadata['pull_request_number'])->toBe(0);
});

it('handles non-integer pull request number in metadata gracefully', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 'not-a-number',
            'repository_full_name' => 'acme/tool',
        ],
    ]);

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logFailed($run, new RuntimeException('Error'));

    $activity = Activity::where('workspace_id', $workspace->id)->first();

    expect($activity->description)->toContain('PR #0')
        ->and($activity->metadata['pull_request_number'])->toBe(0);
});

it('handles non-string repository full name in metadata gracefully', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create([
        'metadata' => [
            'pull_request_number' => 5,
            'repository_full_name' => 12345,
        ],
    ]);

    $logger = app(ReviewRunActivityLogger::class);
    $logger->logFailed($run, new RuntimeException('Error'));

    $activity = Activity::where('workspace_id', $workspace->id)->first();

    expect($activity->description)->toContain('in unknown');
});
