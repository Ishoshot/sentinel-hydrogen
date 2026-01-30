<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);

    $this->workspace->teamMembers()->create([
        'user_id' => $this->user->id,
        'team_id' => $this->workspace->team->id,
        'role' => 'owner',
        'joined_at' => now(),
    ]);

    $this->actingAs($this->user);
});

test('overview metrics returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.overview', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'total_runs',
                'total_findings',
                'average_duration_seconds',
                'active_repositories',
            ],
        ]);
});

test('overview metrics calculates correct values', function (): void {
    $repository = Repository::factory()->create(['workspace_id' => $this->workspace->id]);

    Run::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $repository->id,
        'duration_seconds' => 120,
        'created_at' => now(),
    ]);

    $runWithFindings = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $repository->id,
        'duration_seconds' => 120,
        'created_at' => now(),
    ]);

    Finding::factory()->count(10)->create([
        'workspace_id' => $this->workspace->id,
        'run_id' => $runWithFindings->id,
    ]);

    $response = $this->getJson(route('analytics.overview', $this->workspace));

    $response->assertSuccessful();

    $data = $response->json('data');
    expect($data['total_runs'])->toBe(6)
        ->and($data['total_findings'])->toBe(10)
        ->and($data['average_duration_seconds'])->toBe(120);
});

test('run activity timeline returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.timeline', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('run activity timeline returns timeline data', function (): void {
    $repository = Repository::factory()->create(['workspace_id' => $this->workspace->id]);

    Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $repository->id,
        'status' => 'completed',
        'created_at' => now(),
    ]);

    Run::factory()->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $repository->id,
        'status' => 'failed',
        'created_at' => now(),
    ]);

    $response = $this->getJson(route('analytics.timeline', $this->workspace));

    $response->assertSuccessful();
    expect($response->json('data'))->toBeArray()
        ->and($response->json('data.0'))->toHaveKeys(['date', 'count', 'successful', 'failed']);
});

test('findings distribution returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.findings-distribution', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('findings distribution groups by severity', function (): void {
    $run = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    Finding::factory()->count(3)->create([
        'workspace_id' => $this->workspace->id,
        'run_id' => $run->id,
        'severity' => 'critical',
    ]);

    Finding::factory()->count(2)->create([
        'workspace_id' => $this->workspace->id,
        'run_id' => $run->id,
        'severity' => 'high',
    ]);

    $response = $this->getJson(route('analytics.findings-distribution', $this->workspace));

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data)->toBeArray()
        ->and(count($data))->toBe(2);
});

test('top categories returns limited results', function (): void {
    $run = Run::factory()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $categories = [
        FindingCategory::Security,
        FindingCategory::Performance,
        FindingCategory::Maintainability,
        FindingCategory::Testing,
        FindingCategory::Style,
        FindingCategory::Documentation,
        FindingCategory::Correctness,
        FindingCategory::Reliability,
    ];

    foreach ($categories as $category) {
        Finding::factory()->create([
            'workspace_id' => $this->workspace->id,
            'run_id' => $run->id,
            'category' => $category,
        ]);
    }

    $response = $this->getJson(route('analytics.top-categories', $this->workspace).'?limit=5');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(5);
});

test('repository activity returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.repository-activity', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('developer leaderboard returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.developer-leaderboard', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('developer leaderboard shows active developers', function (): void {
    $developer = User::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $this->workspace->id]);

    Run::factory()->count(5)->create([
        'workspace_id' => $this->workspace->id,
        'repository_id' => $repository->id,
        'initiated_by_id' => $developer->id,
        'status' => 'completed',
    ]);

    $response = $this->getJson(route('analytics.developer-leaderboard', $this->workspace));

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data)->toBeArray()
        ->and($data[0])->toHaveKeys(['id', 'name', 'email', 'runs_count']);
});

test('review duration trends returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.duration-trends', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('token usage returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.token-usage', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('success rate returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.success-rate', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('quality score trend returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.quality-score', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('resolution rate returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.resolution-rate', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('review velocity returns correct structure', function (): void {
    $response = $this->getJson(route('analytics.velocity', $this->workspace));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('analytics endpoints require authentication', function (): void {
    $this->app['auth']->forgetGuards();

    $response = $this->getJson(route('analytics.overview', $this->workspace));

    $response->assertUnauthorized();
});

test('analytics endpoints require workspace access', function (): void {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['owner_id' => $otherUser->id]);

    $response = $this->getJson(route('analytics.overview', $otherWorkspace));

    $response->assertForbidden();
});
