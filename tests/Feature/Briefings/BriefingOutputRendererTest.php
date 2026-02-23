<?php

declare(strict_types=1);

use App\Models\Briefing;
use App\Models\BriefingGeneration;
use App\Models\Workspace;
use App\Services\Briefings\BriefingOutputRenderer;

beforeEach(function (): void {
    $this->renderer = new BriefingOutputRenderer;
    $this->workspace = Workspace::factory()->create();
    $this->briefing = Briefing::factory()->create(['output_formats' => ['html', 'pdf', 'markdown']]);
    $this->generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create([
            'narrative' => 'This was a productive week.',
            'achievements' => [
                ['title' => 'Century Club', 'description' => 'Team merged 100 PRs'],
            ],
            'structured_data' => [
                'slides' => [
                    'slides' => [
                        ['type' => 'title', 'headline' => 'Weekly Summary'],
                        ['type' => 'stat_hero', 'value' => 47, 'label' => 'PRs Merged'],
                    ],
                ],
            ],
        ]);
});

// --- resolveFormats ---

it('resolves output formats from briefing configuration', function (): void {
    $formats = $this->renderer->resolveFormats($this->generation);

    expect($formats->isEmpty())->toBeFalse()
        ->and($formats->toArray())->toBe(['html', 'pdf', 'markdown']);
});

it('throws when briefing has no output formats configured', function (): void {
    $briefing = Briefing::factory()->create(['output_formats' => []]);
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($briefing)
        ->completed()
        ->create();

    $this->renderer->resolveFormats($generation);
})->throws(RuntimeException::class, 'Briefing output formats are not configured.');

it('throws when briefing relationship is null', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create();

    // Force null briefing relationship to test null branch
    $generation->setRelation('briefing', null);

    $this->renderer->resolveFormats($generation);
})->throws(RuntimeException::class, 'Briefing output formats are not configured.');

// --- storageDisk ---

it('returns configured storage disk', function (): void {
    config()->set('briefings.storage.disk', 's3');

    expect($this->renderer->storageDisk())->toBe('s3');
});

it('returns empty string when storage disk config is null', function (): void {
    config()->set('briefings.storage.disk', null);

    expect($this->renderer->storageDisk())->toBe('');
});

it('uses config default when storage disk key is not set', function (): void {
    // When the entire briefings.storage config section doesn't contain disk key,
    // the config helper returns the default 's3'
    config()->set('briefings.storage', []);

    expect($this->renderer->storageDisk())->toBe('s3');
});

// --- resolveStoragePath ---

it('resolves storage path for a generation artifact', function (): void {
    config()->set('briefings.storage.path', 'briefings');

    $path = $this->renderer->resolveStoragePath($this->generation, 'output.html');

    expect($path)->toBe(sprintf(
        'briefings/%d/%d/output.html',
        $this->generation->workspace_id,
        $this->generation->id,
    ));
});

it('uses custom storage path from config', function (): void {
    config()->set('briefings.storage.path', 'custom/path');

    $path = $this->renderer->resolveStoragePath($this->generation, 'report.pdf');

    expect($path)->toContain('custom/path');
});

// --- renderMarkdown ---

it('renders markdown with briefing title and narrative', function (): void {
    $markdown = $this->renderer->renderMarkdown($this->generation);

    expect($markdown)->toContain("# {$this->briefing->title}")
        ->and($markdown)->toContain('This was a productive week.');
});

it('renders markdown with achievements section', function (): void {
    $markdown = $this->renderer->renderMarkdown($this->generation);

    expect($markdown)->toContain('## Achievements')
        ->and($markdown)->toContain('**Century Club**')
        ->and($markdown)->toContain('Team merged 100 PRs');
});

it('renders markdown without achievements section when empty', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create([
            'narrative' => 'Quiet week.',
            'achievements' => [],
        ]);

    $markdown = $this->renderer->renderMarkdown($generation);

    expect($markdown)->not->toContain('## Achievements');
});

it('renders markdown with empty narrative gracefully', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->create([
            'narrative' => null,
            'achievements' => null,
        ]);

    $markdown = $this->renderer->renderMarkdown($generation);

    expect($markdown)->toContain("# {$this->briefing->title}");
});

// --- renderSlides ---

it('renders slides as pretty-printed JSON', function (): void {
    $json = $this->renderer->renderSlides($this->generation);

    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['slides'])->toBeArray()
        ->and($decoded['slides'])->toHaveCount(2);
});

it('throws when slides payload is missing', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create([
            'structured_data' => [],
        ]);

    $this->renderer->renderSlides($generation);
})->throws(RuntimeException::class, 'Briefing slides payload is missing or invalid.');

it('throws when slides payload has invalid structure', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create([
            'structured_data' => [
                'slides' => 'not-an-array',
            ],
        ]);

    $this->renderer->renderSlides($generation);
})->throws(RuntimeException::class, 'Briefing slides payload is missing or invalid.');

it('throws when slides array is missing inner slides key', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->completed()
        ->create([
            'structured_data' => [
                'slides' => ['title' => 'Summary'],
            ],
        ]);

    $this->renderer->renderSlides($generation);
})->throws(RuntimeException::class, 'Briefing slides payload is missing or invalid.');

it('throws when structured data is null', function (): void {
    $generation = BriefingGeneration::factory()
        ->forWorkspace($this->workspace)
        ->forBriefing($this->briefing)
        ->create([
            'structured_data' => null,
        ]);

    $this->renderer->renderSlides($generation);
})->throws(RuntimeException::class, 'Briefing slides payload is missing or invalid.');

// --- renderHtml ---

it('renders HTML using the briefings render view', function (): void {
    $html = $this->renderer->renderHtml($this->generation);

    expect($html)->toBeString()
        ->and(mb_strlen($html))->toBeGreaterThan(0);
});
