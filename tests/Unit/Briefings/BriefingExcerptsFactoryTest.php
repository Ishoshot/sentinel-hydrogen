<?php

declare(strict_types=1);

use App\Services\Briefings\Factories\BriefingExcerptsFactory;
use App\Services\Briefings\ValueObjects\BriefingSummary;

beforeEach(function (): void {
    $this->factory = new BriefingExcerptsFactory;
    $this->summary = BriefingSummary::fromArray([
        'completed' => 10,
        'in_progress' => 3,
        'failed' => 0,
        'repository_count' => 2,
    ]);
});

it('normalizes windows line endings in email excerpt', function (): void {
    $narrative = "First paragraph.\r\n\r\nSecond paragraph.\r\n\r\nThird paragraph.";

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('email'))->toBe("First paragraph.\n\nSecond paragraph.");
});

it('normalizes carriage returns in email excerpt', function (): void {
    $narrative = "First paragraph.\r\rSecond paragraph.\r\rThird paragraph.";

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('email'))->toBe("First paragraph.\n\nSecond paragraph.");
});

it('collapses excessive newlines in email excerpt', function (): void {
    $narrative = "First paragraph.\n\n\n\n\nSecond paragraph.\n\n\nThird paragraph.";

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('email'))->toBe("First paragraph.\n\nSecond paragraph.");
});

it('handles standard double newlines in email excerpt', function (): void {
    $narrative = "First paragraph.\n\nSecond paragraph.\n\nThird paragraph.";

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('email'))->toBe("First paragraph.\n\nSecond paragraph.");
});

it('normalizes line endings in slack excerpt', function (): void {
    $narrative = "Headline paragraph.\r\n\r\nSecond paragraph.";

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('slack'))->toContain('Headline paragraph.');
});

it('returns summary sentence for empty email narrative', function (): void {
    $excerpts = $this->factory->generate('', $this->summary);

    expect($excerpts->get('email'))->toContain('10 completed');
});

it('normalizes line endings in linkedin excerpt', function (): void {
    $narrative = "Some narrative with\r\nwindows line endings\r\nand more content here.";

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('linkedin'))->not->toContain("\r");
});

it('handles single paragraph narrative in email excerpt', function (): void {
    $narrative = 'Just one paragraph with no breaks.';

    $excerpts = $this->factory->generate($narrative, $this->summary);

    expect($excerpts->get('email'))->toBe('Just one paragraph with no breaks.');
});
