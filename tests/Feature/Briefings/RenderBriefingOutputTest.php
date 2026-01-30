<?php

declare(strict_types=1);

use App\Jobs\Briefings\RenderBriefingPdf;
use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\ValueObjects\BriefingPeriod;
use App\Services\Briefings\ValueObjects\BriefingSlide;
use App\Services\Briefings\ValueObjects\BriefingSlideBlock;
use App\Services\Briefings\ValueObjects\BriefingSlideDeck;
use Illuminate\Support\Facades\Storage;

it('renders only requested output formats and stores slides payload', function (): void {
    Storage::fake('s3');

    config()->set('briefings.storage.disk', 's3');
    config()->set('briefings.pdf.driver', null);

    $workspace = Workspace::factory()->create();

    $briefing = Briefing::factory()->create([
        'workspace_id' => $workspace->id,
        'output_formats' => ['html', 'markdown', 'slides'],
    ]);

    $generation = BriefingGeneration::factory()->create([
        'workspace_id' => $workspace->id,
        'briefing_id' => $briefing->id,
        'narrative' => 'Summary narrative.',
        'structured_data' => [
            'slides' => new BriefingSlideDeck(
                version: '1.0',
                title: 'Weekly Summary',
                period: new BriefingPeriod('2025-01-01', '2025-01-07'),
                generatedAt: '2025-01-08T00:00:00Z',
                slides: [
                    new BriefingSlide(
                        id: 'title',
                        type: 'title',
                        title: 'Weekly Summary',
                        subtitle: null,
                        blocks: [BriefingSlideBlock::text('Summary narrative.')],
                    ),
                ],
                meta: ['briefing_slug' => 'weekly-team-summary'],
            )->toArray(),
        ],
        'achievements' => [],
        'output_paths' => null,
    ]);

    $job = new RenderBriefingPdf($generation);
    $job->handle();

    $generation->refresh();

    expect(array_keys($generation->output_paths ?? []))
        ->toEqualCanonicalizing(['html', 'markdown', 'slides']);

    $slidesPath = $generation->output_paths['slides'] ?? null;
    expect($slidesPath)->not()->toBeNull();

    $slidesJson = Storage::disk('s3')->get($slidesPath);
    $payload = json_decode($slidesJson, true);

    expect($payload)->toHaveKey('slides')
        ->and($payload['slides'][0]['type'] ?? null)->toBe('title')
        ->and($payload['version'] ?? null)->toBe('1.0');
});
