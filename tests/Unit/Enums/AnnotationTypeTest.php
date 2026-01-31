<?php

declare(strict_types=1);

use App\Enums\AnnotationType;

it('returns all values', function (): void {
    $values = AnnotationType::values();

    expect($values)->toBeArray()
        ->toContain('inline')
        ->toContain('summary');
});

it('returns correct label for inline', function (): void {
    expect(AnnotationType::Inline->label())->toBe('Inline Comment');
});

it('returns correct label for summary', function (): void {
    expect(AnnotationType::Summary->label())->toBe('Summary Comment');
});
