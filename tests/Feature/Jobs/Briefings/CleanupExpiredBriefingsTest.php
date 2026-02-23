<?php

declare(strict_types=1);

use App\Actions\Briefings\PurgeExpiredBriefings;
use App\Jobs\Briefings\CleanupExpiredBriefings;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\Workspace;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

it('deletes expired briefing generations', function (): void {
    $workspace = Workspace::factory()->create();

    $expiredGeneration = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->subDay(),
        ]);

    $validGeneration = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->addDay(),
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingGeneration::find($expiredGeneration->id))->toBeNull();
    expect(BriefingGeneration::find($validGeneration->id))->not->toBeNull();
});

it('deletes related shares when generation is deleted', function (): void {
    $workspace = Workspace::factory()->create();

    $expiredGeneration = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->subDay(),
        ]);

    $share = BriefingShare::factory()
        ->forGeneration($expiredGeneration)
        ->create();

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingShare::find($share->id))->toBeNull();
});

it('deletes expired shares independently', function (): void {
    $workspace = Workspace::factory()->create();

    $generation = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->addDays(30),
        ]);

    $expiredShare = BriefingShare::factory()
        ->forGeneration($generation)
        ->expired()
        ->create();

    $validShare = BriefingShare::factory()
        ->forGeneration($generation)
        ->create([
            'expires_at' => now()->addDay(),
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingShare::find($expiredShare->id))->toBeNull();
    expect(BriefingShare::find($validShare->id))->not->toBeNull();
});

it('attempts to delete storage files for expired generations', function (): void {
    Storage::fake('s3');

    $workspace = Workspace::factory()->create();

    $generation = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->subDay(),
            'output_paths' => [
                'html' => 'briefings/1/1/html.html',
                'pdf' => 'briefings/1/1/pdf.pdf',
            ],
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingGeneration::find($generation->id))->toBeNull();
});

it('logs cleanup results', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->subDay(),
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Cleaned up expired briefing generations'));
});

it('handles generations without expiry date', function (): void {
    $workspace = Workspace::factory()->create();

    $generationWithoutExpiry = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => null,
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingGeneration::find($generationWithoutExpiry->id))->not->toBeNull();
});

it('processes expired generations in chunks', function (): void {
    Log::spy();

    Config::set('briefings.retention.cleanup_batch_size', 2);

    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->count(5)
        ->create([
            'expires_at' => now()->subDay(),
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingGeneration::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->count()
    )->toBe(0);

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Starting cleanup of expired briefing generations'));

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Processed cleanup chunk'));
});

it('logs total count before processing', function (): void {
    Log::spy();

    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->count(3)
        ->create([
            'expires_at' => now()->subDay(),
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Starting cleanup of expired briefing generations')
                && ($context['total_expired'] ?? 0) === 3;
        });
});

it('continues cleanup when storage deletion fails', function (): void {
    Storage::shouldReceive('disk')
        ->andReturnSelf();
    Storage::shouldReceive('deleteDirectory')
        ->andThrow(new RuntimeException('Storage unavailable'));

    $workspace = Workspace::factory()->create();

    $generation1 = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->subDay(),
            'output_paths' => ['html' => 'briefings/1/1/html.html'],
        ]);

    $generation2 = BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->create([
            'expires_at' => now()->subDay(),
            'output_paths' => ['html' => 'briefings/1/2/html.html'],
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingGeneration::find($generation1->id))->toBeNull();
    expect(BriefingGeneration::find($generation2->id))->toBeNull();
});

it('respects configurable batch size', function (): void {
    Config::set('briefings.retention.cleanup_batch_size', 1);
    Log::spy();

    $workspace = Workspace::factory()->create();

    BriefingGeneration::factory()
        ->forWorkspace($workspace)
        ->completed()
        ->count(3)
        ->create([
            'expires_at' => now()->subDay(),
        ]);

    (new CleanupExpiredBriefings)->handle(app(PurgeExpiredBriefings::class));

    expect(BriefingGeneration::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->count()
    )->toBe(0);

    Log::shouldHaveReceived('info')
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Starting cleanup of expired briefing generations')
                && ($context['chunk_size'] ?? 0) === 1;
        });
});
