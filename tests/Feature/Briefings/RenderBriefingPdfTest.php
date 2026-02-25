<?php

declare(strict_types=1);

use App\Jobs\Briefings\RenderBriefingPdf;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use Illuminate\Support\Facades\Storage;

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

    expect(fn () => app()->call([$job, 'handle']))
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

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(RuntimeException::class, 'Briefing slides payload is missing or invalid.');
});

test('failed method cleans up orphaned files from storage', function () {
    Storage::fake('s3');
    config(['briefings.storage.disk' => 's3']);

    $generation = BriefingGeneration::factory()
        ->completed()
        ->create();

    $basePath = sprintf('briefings/%d/%d', $generation->workspace_id, $generation->id);
    Storage::disk('s3')->put($basePath.'/briefing.html', 'html content');
    Storage::disk('s3')->put($basePath.'/briefing.md', 'markdown content');

    expect(Storage::disk('s3')->files($basePath))->toHaveCount(2);

    $job = new RenderBriefingPdf($generation);
    $job->failed(new RuntimeException('PDF rendering failed'));

    expect(Storage::disk('s3')->files($basePath))->toHaveCount(0);
});
