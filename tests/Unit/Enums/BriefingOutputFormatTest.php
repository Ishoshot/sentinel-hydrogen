<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingOutputFormat;

it('returns all values', function (): void {
    $values = BriefingOutputFormat::values();

    expect($values)->toBeArray()
        ->toContain('html')
        ->toContain('pdf')
        ->toContain('markdown')
        ->toContain('slides');
});

it('returns correct file extensions', function (): void {
    expect(BriefingOutputFormat::Html->extension())->toBe('html');
    expect(BriefingOutputFormat::Pdf->extension())->toBe('pdf');
    expect(BriefingOutputFormat::Markdown->extension())->toBe('md');
    expect(BriefingOutputFormat::Slides->extension())->toBe('json');
});

it('returns correct mime types', function (): void {
    expect(BriefingOutputFormat::Html->mimeType())->toBe('text/html');
    expect(BriefingOutputFormat::Pdf->mimeType())->toBe('application/pdf');
    expect(BriefingOutputFormat::Markdown->mimeType())->toBe('text/markdown');
    expect(BriefingOutputFormat::Slides->mimeType())->toBe('application/json');
});
