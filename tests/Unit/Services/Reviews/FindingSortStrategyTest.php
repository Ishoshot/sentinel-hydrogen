<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Services\Reviews\Strategies\FindingSortStrategy;
use App\Services\Reviews\ValueObjects\ReviewFinding;

function makeSortFinding(
    SentinelConfigSeverity $severity = SentinelConfigSeverity::Medium,
    float $confidence = 0.9,
    ?string $filePath = 'src/App.php',
    ?int $lineStart = 10,
    string $title = 'Test finding',
): ReviewFinding {
    return new ReviewFinding(
        severity: $severity,
        category: FindingCategory::Security,
        title: $title,
        description: 'Test description',
        impact: 'Test impact',
        confidence: $confidence,
        filePath: $filePath,
        lineStart: $lineStart,
    );
}

it('returns empty array when given empty input', function (): void {
    $strategy = new FindingSortStrategy;

    expect($strategy->sort([]))->toBe([]);
});

it('returns single finding unchanged', function (): void {
    $strategy = new FindingSortStrategy;
    $finding = makeSortFinding();

    $result = $strategy->sort([$finding]);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe($finding);
});

it('sorts by severity descending as primary sort', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(severity: SentinelConfigSeverity::Low, title: 'Low'),
        makeSortFinding(severity: SentinelConfigSeverity::Critical, title: 'Critical'),
        makeSortFinding(severity: SentinelConfigSeverity::Medium, title: 'Medium'),
        makeSortFinding(severity: SentinelConfigSeverity::High, title: 'High'),
        makeSortFinding(severity: SentinelConfigSeverity::Info, title: 'Info'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Critical')
        ->and($result[1]->title)->toBe('High')
        ->and($result[2]->title)->toBe('Medium')
        ->and($result[3]->title)->toBe('Low')
        ->and($result[4]->title)->toBe('Info');
});

it('sorts by confidence descending within same severity', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(severity: SentinelConfigSeverity::High, confidence: 0.7, title: 'Low confidence'),
        makeSortFinding(severity: SentinelConfigSeverity::High, confidence: 0.95, title: 'High confidence'),
        makeSortFinding(severity: SentinelConfigSeverity::High, confidence: 0.85, title: 'Mid confidence'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('High confidence')
        ->and($result[1]->title)->toBe('Mid confidence')
        ->and($result[2]->title)->toBe('Low confidence');
});

it('sorts by file path ascending within same severity and confidence', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(filePath: 'src/Zoo.php', title: 'Zoo'),
        makeSortFinding(filePath: 'src/App.php', title: 'App'),
        makeSortFinding(filePath: 'src/Middle.php', title: 'Middle'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('App')
        ->and($result[1]->title)->toBe('Middle')
        ->and($result[2]->title)->toBe('Zoo');
});

it('sorts by line start ascending within same file', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(filePath: 'src/App.php', lineStart: 50, title: 'Line 50'),
        makeSortFinding(filePath: 'src/App.php', lineStart: 10, title: 'Line 10'),
        makeSortFinding(filePath: 'src/App.php', lineStart: 30, title: 'Line 30'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Line 10')
        ->and($result[1]->title)->toBe('Line 30')
        ->and($result[2]->title)->toBe('Line 50');
});

it('sorts by title ascending as final tiebreaker', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(title: 'Zebra issue'),
        makeSortFinding(title: 'Alpha issue'),
        makeSortFinding(title: 'Middle issue'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Alpha issue')
        ->and($result[1]->title)->toBe('Middle issue')
        ->and($result[2]->title)->toBe('Zebra issue');
});

it('sorts null file paths after non-null file paths', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(filePath: null, title: 'No path'),
        makeSortFinding(filePath: 'src/App.php', title: 'Has path'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Has path')
        ->and($result[1]->title)->toBe('No path');
});

it('sorts null line starts after non-null line starts', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(filePath: 'src/App.php', lineStart: null, title: 'No line'),
        makeSortFinding(filePath: 'src/App.php', lineStart: 5, title: 'Has line'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Has line')
        ->and($result[1]->title)->toBe('No line');
});

it('treats two null file paths as equal', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(filePath: null, lineStart: null, title: 'Beta'),
        makeSortFinding(filePath: null, lineStart: null, title: 'Alpha'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Alpha')
        ->and($result[1]->title)->toBe('Beta');
});

it('treats two null line starts as equal', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(filePath: 'src/App.php', lineStart: null, title: 'Beta'),
        makeSortFinding(filePath: 'src/App.php', lineStart: null, title: 'Alpha'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Alpha')
        ->and($result[1]->title)->toBe('Beta');
});

it('applies the full sort cascade correctly', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(severity: SentinelConfigSeverity::Medium, confidence: 0.8, filePath: 'src/B.php', lineStart: 20, title: 'Med/B/20'),
        makeSortFinding(severity: SentinelConfigSeverity::Critical, confidence: 0.9, filePath: 'src/A.php', lineStart: 1, title: 'Crit/A/1'),
        makeSortFinding(severity: SentinelConfigSeverity::Medium, confidence: 0.8, filePath: 'src/A.php', lineStart: 10, title: 'Med/A/10'),
        makeSortFinding(severity: SentinelConfigSeverity::Critical, confidence: 0.95, filePath: 'src/A.php', lineStart: 5, title: 'Crit/A/5'),
        makeSortFinding(severity: SentinelConfigSeverity::Medium, confidence: 0.8, filePath: 'src/A.php', lineStart: 5, title: 'Med/A/5'),
        makeSortFinding(severity: SentinelConfigSeverity::Low, confidence: 0.99, filePath: null, lineStart: null, title: 'Low/null'),
    ];

    $result = $strategy->sort($findings);

    expect($result[0]->title)->toBe('Crit/A/5')
        ->and($result[1]->title)->toBe('Crit/A/1')
        ->and($result[2]->title)->toBe('Med/A/5')
        ->and($result[3]->title)->toBe('Med/A/10')
        ->and($result[4]->title)->toBe('Med/B/20')
        ->and($result[5]->title)->toBe('Low/null');
});

it('preserves array values as re-indexed list', function (): void {
    $strategy = new FindingSortStrategy;

    $findings = [
        makeSortFinding(severity: SentinelConfigSeverity::Low, title: 'Low'),
        makeSortFinding(severity: SentinelConfigSeverity::High, title: 'High'),
    ];

    $result = $strategy->sort($findings);

    expect(array_keys($result))->toBe([0, 1])
        ->and($result[0]->title)->toBe('High');
});
