<?php

declare(strict_types=1);

use App\Jobs\Briefings\RenderBriefingPdf;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('it fails when output formats are not configured', function () {
    Storage::fake('s3');
    config(['briefings.storage.disk' => 's3']);

    $briefing = Briefing::factory()->create([
        'output_formats' => [],
    ]);

    $generation = BriefingGeneration::factory()
        ->forBriefing($briefing)
        ->completed()
        ->create([
            'structured_data' => [
                'slides' => [
                    'slides' => [],
                ],
            ],
        ]);

    $job = new RenderBriefingPdf($generation);

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Briefing output formats are not configured.');
});

test('it fails when slides payload is missing', function () {
    Storage::fake('s3');
    config(['briefings.storage.disk' => 's3']);

    $briefing = Briefing::factory()->create([
        'output_formats' => ['slides'],
    ]);

    $generation = BriefingGeneration::factory()
        ->forBriefing($briefing)
        ->completed()
        ->create([
            'structured_data' => [],
        ]);

    $job = new RenderBriefingPdf($generation);

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Briefing slides payload is missing or invalid.');
});
