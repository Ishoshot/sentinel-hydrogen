<?php

declare(strict_types=1);

use App\Jobs\Briefings\CleanupExpiredBriefings;
use App\Models\BriefingGeneration;
use App\Models\BriefingShare;
use App\Models\Workspace;
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

    (new CleanupExpiredBriefings)->handle();

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

    (new CleanupExpiredBriefings)->handle();

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

    (new CleanupExpiredBriefings)->handle();

    expect(BriefingShare::find($expiredShare->id))->toBeNull();
    expect(BriefingShare::find($validShare->id))->not->toBeNull();
});

it('attempts to delete storage files for expired generations', function (): void {
    Storage::fake('r2');

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

    (new CleanupExpiredBriefings)->handle();

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

    (new CleanupExpiredBriefings)->handle();

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

    (new CleanupExpiredBriefings)->handle();

    expect(BriefingGeneration::find($generationWithoutExpiry->id))->not->toBeNull();
});
