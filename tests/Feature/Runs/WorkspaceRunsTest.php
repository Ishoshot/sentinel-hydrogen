<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\RunStatus;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\RepositorySettings;
use App\Models\Run;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    TeamMember::factory()->forUser($this->user)->forWorkspace($this->workspace)->owner()->create();

    $provider = Provider::query()->firstOrCreate(
        ['type' => ProviderType::GitHub],
        ['name' => 'GitHub', 'is_active' => true]
    );
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($this->workspace)->active()->create();
    $this->installation = Installation::factory()->forConnection($connection)->create();
    $this->repository = Repository::factory()->forInstallation($this->installation)->create([
        'name' => 'test-repo',
        'full_name' => 'org/test-repo',
    ]);
    RepositorySettings::factory()->forRepository($this->repository)->autoReviewEnabled()->create();
});

it('lists all runs for a workspace', function (): void {
    $run1 = Run::factory()->forRepository($this->repository)->create([
        'status' => RunStatus::Completed,
        'metadata' => [
            'pull_request_number' => 1,
            'pull_request_title' => 'First PR',
            'sender_login' => 'user1',
        ],
    ]);
    $run2 = Run::factory()->forRepository($this->repository)->create([
        'status' => RunStatus::InProgress,
        'metadata' => [
            'pull_request_number' => 2,
            'pull_request_title' => 'Second PR',
            'sender_login' => 'user2',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'repository_id',
                    'external_reference',
                    'status',
                    'started_at',
                    'completed_at',
                    'findings_count',
                    'pull_request',
                    'summary',
                    'repository' => [
                        'id',
                        'name',
                        'full_name',
                        'owner',
                        'private',
                        'language',
                    ],
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);
});

it('returns empty list when workspace has no runs', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

it('filters runs by status', function (): void {
    Run::factory()->forRepository($this->repository)->create(['status' => RunStatus::Completed]);
    Run::factory()->forRepository($this->repository)->create(['status' => RunStatus::InProgress]);
    Run::factory()->forRepository($this->repository)->create(['status' => RunStatus::Failed]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?status=completed");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'completed');
});

it('filters runs by repository', function (): void {
    $repo2 = Repository::factory()->forInstallation($this->installation)->create([
        'name' => 'other-repo',
        'full_name' => 'org/other-repo',
    ]);
    RepositorySettings::factory()->forRepository($repo2)->autoReviewEnabled()->create();

    Run::factory()->forRepository($this->repository)->create();
    Run::factory()->forRepository($repo2)->create();
    Run::factory()->forRepository($repo2)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?repository_id={$this->repository->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.repository.id', $this->repository->id);
});

it('filters runs by date range', function (): void {
    Run::factory()->forRepository($this->repository)->create([
        'created_at' => now()->subDays(10),
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'created_at' => now()->subDays(5),
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'created_at' => now()->subDays(1),
    ]);

    $fromDate = now()->subDays(7)->format('Y-m-d');
    $toDate = now()->subDays(3)->format('Y-m-d');

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?from_date={$fromDate}&to_date={$toDate}");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters runs by author', function (): void {
    Run::factory()->forRepository($this->repository)->create([
        'metadata' => ['sender_login' => 'octocat'],
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'metadata' => ['sender_login' => 'anotheruser'],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?author=octocat");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches runs by PR title', function (): void {
    Run::factory()->forRepository($this->repository)->create([
        'metadata' => ['pull_request_title' => 'Add authentication feature'],
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'metadata' => ['pull_request_title' => 'Fix bug in login'],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?search=authentication");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches runs by repository name', function (): void {
    $repo2 = Repository::factory()->forInstallation($this->installation)->create([
        'name' => 'special-repo',
        'full_name' => 'org/special-repo',
    ]);
    RepositorySettings::factory()->forRepository($repo2)->autoReviewEnabled()->create();

    Run::factory()->forRepository($this->repository)->create();
    Run::factory()->forRepository($repo2)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?search=special");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.repository.name', 'special-repo');
});

it('sorts runs by created_at desc by default', function (): void {
    $oldRun = Run::factory()->forRepository($this->repository)->create([
        'created_at' => now()->subDays(2),
    ]);
    $newRun = Run::factory()->forRepository($this->repository)->create([
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $newRun->id)
        ->assertJsonPath('data.1.id', $oldRun->id);
});

it('sorts runs by created_at asc', function (): void {
    $oldRun = Run::factory()->forRepository($this->repository)->create([
        'created_at' => now()->subDays(2),
    ]);
    $newRun = Run::factory()->forRepository($this->repository)->create([
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?sort_by=created_at&sort_order=asc");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $oldRun->id)
        ->assertJsonPath('data.1.id', $newRun->id);
});

it('sorts runs by findings_count', function (): void {
    $runWithManyFindings = Run::factory()->forRepository($this->repository)->create();
    Finding::factory()->forRun($runWithManyFindings)->count(5)->create();

    $runWithFewFindings = Run::factory()->forRepository($this->repository)->create();
    Finding::factory()->forRun($runWithFewFindings)->count(1)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?sort_by=findings_count&sort_order=desc");

    $response->assertOk()
        ->assertJsonPath('data.0.id', $runWithManyFindings->id)
        ->assertJsonPath('data.0.findings_count', 5)
        ->assertJsonPath('data.1.id', $runWithFewFindings->id)
        ->assertJsonPath('data.1.findings_count', 1);
});

it('paginates results', function (): void {
    Run::factory()->forRepository($this->repository)->count(25)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?per_page=10");

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 3);
});

it('limits per_page to 100', function (): void {
    Run::factory()->forRepository($this->repository)->count(5)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?per_page=200");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
});

it('returns 401 for unauthenticated request', function (): void {
    $response = $this->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertUnauthorized();
});

it('returns 403 for user without workspace access', function (): void {
    $otherUser = User::factory()->create();

    $response = $this->actingAs($otherUser)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertForbidden();
});

it('validates status parameter', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?status=invalid");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('validates risk_level parameter', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?risk_level=invalid");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['risk_level']);
});

it('validates sort_by parameter', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?sort_by=invalid_field");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sort_by']);
});

it('validates date format', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?from_date=invalid-date");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['from_date']);
});

it('validates to_date is after from_date', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?from_date=2026-01-10&to_date=2026-01-05");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['to_date']);
});

it('includes findings_count in response', function (): void {
    $run = Run::factory()->forRepository($this->repository)->create();
    Finding::factory()->forRun($run)->count(3)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonPath('data.0.findings_count', 3);
});

it('always includes repository in response', function (): void {
    Run::factory()->forRepository($this->repository)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'repository' => [
                        'id',
                        'name',
                        'full_name',
                        'owner',
                        'private',
                        'language',
                    ],
                ],
            ],
        ]);
});

it('only returns runs from accessible repositories', function (): void {
    $otherWorkspace = Workspace::factory()->create();
    $provider = Provider::query()->where('type', ProviderType::GitHub)->first();
    $otherConnection = Connection::factory()->forProvider($provider)->forWorkspace($otherWorkspace)->active()->create();
    $otherInstallation = Installation::factory()->forConnection($otherConnection)->create();
    $otherRepo = Repository::factory()->forInstallation($otherInstallation)->create();
    RepositorySettings::factory()->forRepository($otherRepo)->autoReviewEnabled()->create();

    Run::factory()->forRepository($this->repository)->create();
    Run::factory()->forRepository($otherRepo)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.repository.id', $this->repository->id);
});

it('filters runs by risk level from summary', function (): void {
    Run::factory()->forRepository($this->repository)->create([
        'metadata' => [
            'pull_request_number' => 1,
            'review_summary' => ['risk_level' => 'high'],
        ],
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'metadata' => [
            'pull_request_number' => 2,
            'review_summary' => ['risk_level' => 'low'],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?risk_level=high");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('returns runs from multiple repositories in the workspace', function (): void {
    $repo2 = Repository::factory()->forInstallation($this->installation)->create([
        'name' => 'second-repo',
        'full_name' => 'org/second-repo',
    ]);
    RepositorySettings::factory()->forRepository($repo2)->autoReviewEnabled()->create();

    Run::factory()->forRepository($this->repository)->count(2)->create();
    Run::factory()->forRepository($repo2)->count(3)->create();

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs");

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 5);
});

it('groups runs by pull request', function (): void {
    Run::factory()->forRepository($this->repository)->create([
        'pr_number' => 1,
        'pr_title' => 'First PR',
        'status' => RunStatus::Completed,
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'pr_number' => 1,
        'pr_title' => 'First PR',
        'status' => RunStatus::Completed,
    ]);
    Run::factory()->forRepository($this->repository)->create([
        'pr_number' => 2,
        'pr_title' => 'Second PR',
        'status' => RunStatus::InProgress,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?group_by=pr");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'pull_request_number',
                    'pull_request_title',
                    'repository',
                    'runs_count',
                    'latest_run',
                    'latest_status',
                    'runs',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('groups runs by repository', function (): void {
    $repo2 = Repository::factory()->forInstallation($this->installation)->create([
        'name' => 'second-repo',
        'full_name' => 'org/second-repo',
    ]);
    RepositorySettings::factory()->forRepository($repo2)->autoReviewEnabled()->create();

    Run::factory()->forRepository($this->repository)->count(3)->create([
        'pr_number' => 1,
        'pr_title' => 'Test PR',
    ]);
    Run::factory()->forRepository($repo2)->count(2)->create([
        'pr_number' => 2,
        'pr_title' => 'Other PR',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?group_by=repository");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'repository',
                    'pull_requests_count',
                    'runs_count',
                    'pull_requests',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

it('paginates grouped by PR results at database level', function (): void {
    for ($i = 1; $i <= 25; $i++) {
        Run::factory()->forRepository($this->repository)->create([
            'pr_number' => $i,
            'pr_title' => "PR {$i}",
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?group_by=pr&per_page=10");

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.last_page', 3);
});

it('paginates grouped by repository results at database level', function (): void {
    for ($i = 1; $i <= 15; $i++) {
        $repo = Repository::factory()->forInstallation($this->installation)->create([
            'name' => "repo-{$i}",
            'full_name' => "org/repo-{$i}",
        ]);
        RepositorySettings::factory()->forRepository($repo)->autoReviewEnabled()->create();
        Run::factory()->forRepository($repo)->create([
            'pr_number' => $i,
            'pr_title' => "PR {$i}",
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?group_by=repository&per_page=5");

    $response->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 15)
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.last_page', 3);
});

it('limits runs per PR group', function (): void {
    for ($i = 0; $i < 15; $i++) {
        Run::factory()->forRepository($this->repository)->create([
            'pr_number' => 1,
            'pr_title' => 'PR with many runs',
            'created_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?group_by=pr");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.runs_count', 15);

    $runsInResponse = $response->json('data.0.runs');
    expect(count($runsInResponse))->toBeLessThanOrEqual(10);
});

it('validates group_by parameter', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs?group_by=invalid");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['group_by']);
});

it('returns policy snapshot details and finding hash for a run', function (): void {
    $run = Run::factory()->forRepository($this->repository)->create([
        'policy_snapshot' => [
            'policy_version' => 1,
            'enabled_rules' => ['security', 'maintainability'],
            'severity_thresholds' => ['comment' => 'low'],
            'confidence_thresholds' => ['finding' => 0.7],
            'comment_limits' => ['max_inline_comments' => 25],
            'ignored_paths' => ['vendor/**'],
            'config_source' => 'branch',
            'config_branch' => 'main',
        ],
    ]);

    $finding = Finding::factory()->forRun($run)->create([
        'finding_hash' => 'f75ce4ecb01c9a98a8cfa0c8f1d2b8a2d1bda3f5c1e1f7d9a2c3e4f5a6b7c8d9',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/workspaces/{$this->workspace->id}/runs/{$run->id}");

    $response->assertOk()
        ->assertJsonPath('data.policy_snapshot.config_source', 'branch')
        ->assertJsonPath('data.policy_snapshot.config_branch', 'main')
        ->assertJsonPath('data.policy_snapshot.confidence_thresholds.finding', 0.7)
        ->assertJsonMissingPath('data.findings.0.finding_hash');
});
