<?php

declare(strict_types=1);

use App\Services\Reviews\Support\PrismReviewResultMapper;

it('maps summary with defaults for invalid enum values', function (): void {
    $mapper = new PrismReviewResultMapper;

    $summary = $mapper->mapSummary([
        'overview' => 'Summary text',
        'verdict' => 'invalid',
        'risk_level' => 'invalid',
        'strengths' => ['good tests', 123],
        'concerns' => ['edge cases'],
        'recommendations' => ['add coverage'],
    ]);

    expect($summary->overview)->toBe('Summary text')
        ->and($summary->verdict->value)->toBe('comment')
        ->and($summary->riskLevel->value)->toBe('low')
        ->and($summary->strengths)->toBe(['good tests']);
});

it('maps findings and drops invalid entries', function (): void {
    $mapper = new PrismReviewResultMapper;

    $findings = $mapper->mapFindings([
        [
            'severity' => 'medium',
            'category' => 'security',
            'title' => 'Issue title',
            'description' => 'Issue description',
            'confidence' => 4.5,
            'impact' => 'High impact',
            'file_path' => 'app/Service.php',
            'line_start' => 10,
            'references' => ['[OWASP](https://owasp.org)', 123],
        ],
        [
            'severity' => 'low',
        ],
    ]);

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity->value)->toBe('medium')
        ->and($findings[0]->confidence)->toBe(1.0)
        ->and($findings[0]->references)->toBe(['[OWASP](https://owasp.org)']);
});
