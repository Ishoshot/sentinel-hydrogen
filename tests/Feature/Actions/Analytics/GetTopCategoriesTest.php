<?php

declare(strict_types=1);

use App\Actions\Analytics\GetTopCategories;
use App\Enums\Reviews\FindingCategory;
use App\Models\Finding;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;

it('returns top categories', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->forRun($run)->create(['category' => FindingCategory::Security]);

    $action = new GetTopCategories;
    $result = $action->handle($workspace, 10);

    expect($result)->toHaveCount(1);
    expect($result->first())->toHaveKeys(['category', 'count']);
});

it('counts findings per category correctly', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(3)->forRun($run)->create(['category' => FindingCategory::Security]);
    Finding::factory()->count(2)->forRun($run)->create(['category' => FindingCategory::Performance]);

    $action = new GetTopCategories;
    $result = $action->handle($workspace, 10);

    $securityEntry = $result->firstWhere('category', FindingCategory::Security->value);
    $performanceEntry = $result->firstWhere('category', FindingCategory::Performance->value);

    expect($securityEntry['count'])->toBe(3);
    expect($performanceEntry['count'])->toBe(2);
});

it('orders by count descending', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    Finding::factory()->count(5)->forRun($run)->create(['category' => FindingCategory::Security]);
    Finding::factory()->count(10)->forRun($run)->create(['category' => FindingCategory::Performance]);

    $action = new GetTopCategories;
    $result = $action->handle($workspace, 10);

    expect($result->first()['category'])->toBe(FindingCategory::Performance->value);
    expect($result->first()['count'])->toBe(10);
});

it('respects limit parameter', function (): void {
    $workspace = Workspace::factory()->create();
    $repository = Repository::factory()->create(['workspace_id' => $workspace->id]);
    $run = Run::factory()->forRepository($repository)->create();

    $categories = [
        FindingCategory::Security,
        FindingCategory::Performance,
        FindingCategory::Maintainability,
        FindingCategory::Testing,
        FindingCategory::Style,
    ];

    foreach ($categories as $category) {
        Finding::factory()->forRun($run)->create(['category' => $category]);
    }

    $action = new GetTopCategories;
    $result = $action->handle($workspace, 3);

    expect($result)->toHaveCount(3);
});

it('returns empty collection when no findings', function (): void {
    $workspace = Workspace::factory()->create();

    $action = new GetTopCategories;
    $result = $action->handle($workspace, 10);

    expect($result)->toBeEmpty();
});

it('only includes findings for the specified workspace', function (): void {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $repo1 = Repository::factory()->create(['workspace_id' => $workspace1->id]);
    $repo2 = Repository::factory()->create(['workspace_id' => $workspace2->id]);

    $run1 = Run::factory()->forRepository($repo1)->create();
    $run2 = Run::factory()->forRepository($repo2)->create();

    Finding::factory()->count(2)->forRun($run1)->create(['category' => FindingCategory::Security]);
    Finding::factory()->count(5)->forRun($run2)->create(['category' => FindingCategory::Security]);

    $action = new GetTopCategories;
    $result = $action->handle($workspace1, 10);

    $securityEntry = $result->firstWhere('category', FindingCategory::Security->value);

    expect($securityEntry['count'])->toBe(2);
});
