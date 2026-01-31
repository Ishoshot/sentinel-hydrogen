<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Http\Resources\FindingResource;
use App\Models\Annotation;
use App\Models\Connection;
use App\Models\Finding;
use App\Models\Installation;
use App\Models\Provider;
use App\Models\Repository;
use App\Models\Run;
use App\Models\Workspace;
use Illuminate\Http\Request;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('transforms a finding to array', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();

    $finding = Finding::factory()->create([
        'run_id' => $run->id,
        'workspace_id' => $workspace->id,
        'severity' => SentinelConfigSeverity::Medium,
        'category' => FindingCategory::Security,
        'title' => 'Test Finding',
        'description' => 'A test finding description',
        'file_path' => 'src/test.php',
        'line_start' => 10,
        'line_end' => 20,
        'confidence' => 0.95,
        'metadata' => ['key' => 'value'],
    ]);

    $resource = new FindingResource($finding);
    $request = new Request;

    $array = $resource->toArray($request);

    expect($array)->toBeArray()
        ->and($array['id'])->toBe($finding->id)
        ->and($array['run_id'])->toBe($finding->run_id)
        ->and($array['severity'])->toBe(SentinelConfigSeverity::Medium)
        ->and($array['category'])->toBe(FindingCategory::Security)
        ->and($array['title'])->toBe('Test Finding')
        ->and($array['description'])->toBe('A test finding description')
        ->and($array['file_path'])->toBe('src/test.php')
        ->and($array['line_start'])->toBe(10)
        ->and($array['line_end'])->toBe(20)
        ->and($array['confidence'])->toBe('0.95')
        ->and($array['metadata'])->toBe(['key' => 'value'])
        ->and($array['created_at'])->toBe($finding->created_at->toISOString());
});

it('includes annotations when loaded', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();
    $finding = Finding::factory()->forRun($run)->create();

    Annotation::factory()->count(2)->create([
        'finding_id' => $finding->id,
        'workspace_id' => $workspace->id,
        'provider_id' => $provider->id,
    ]);

    $finding->load('annotations');

    $resource = new FindingResource($finding);
    $request = new Request;

    $array = $resource->toArray($request);

    expect($array['annotations'])->toHaveCount(2);
});
