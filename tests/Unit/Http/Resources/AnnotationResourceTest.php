<?php

declare(strict_types=1);

use App\Enums\Auth\ProviderType;
use App\Http\Resources\AnnotationResource;
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

it('transforms an annotation to array', function (): void {
    $workspace = Workspace::factory()->create();
    $provider = Provider::where('type', ProviderType::GitHub)->first();
    $connection = Connection::factory()->forProvider($provider)->forWorkspace($workspace)->create();
    $installation = Installation::factory()->forConnection($connection)->create();
    $repository = Repository::factory()->forInstallation($installation)->create();
    $run = Run::factory()->forRepository($repository)->create();
    $finding = Finding::factory()->forRun($run)->create();

    $annotation = Annotation::factory()->create([
        'finding_id' => $finding->id,
        'workspace_id' => $workspace->id,
        'provider_id' => $provider->id,
        'external_id' => 'ext-123',
        'type' => 'inline',
    ]);

    $resource = new AnnotationResource($annotation);
    $request = new Request;

    $array = $resource->toArray($request);

    expect($array)->toBeArray()
        ->and($array['id'])->toBe($annotation->id)
        ->and($array['provider_id'])->toBe($annotation->provider_id)
        ->and($array['external_id'])->toBe('ext-123')
        ->and($array['type'])->toBe('inline')
        ->and($array['created_at'])->toBe($annotation->created_at->toISOString());
});
