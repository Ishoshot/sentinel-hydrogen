<?php

declare(strict_types=1);

use App\Enums\Reviews\FindingCategory;
use App\Enums\SentinelConfig\SentinelConfigSeverity;
use App\Services\Reviews\ValueObjects\ReviewFinding;

it('can be constructed with required parameters', function (): void {
    $finding = new ReviewFinding(
        severity: SentinelConfigSeverity::High,
        category: FindingCategory::Security,
        title: 'SQL Injection',
        description: 'User input is not sanitized',
        impact: 'Data breach possible',
        confidence: 0.95,
    );

    expect($finding->severity)->toBe(SentinelConfigSeverity::High);
    expect($finding->category)->toBe(FindingCategory::Security);
    expect($finding->title)->toBe('SQL Injection');
    expect($finding->description)->toBe('User input is not sanitized');
    expect($finding->impact)->toBe('Data breach possible');
    expect($finding->confidence)->toBe(0.95);
});

it('can be constructed with all parameters', function (): void {
    $finding = new ReviewFinding(
        severity: SentinelConfigSeverity::Critical,
        category: FindingCategory::Correctness,
        title: 'Bug Fix',
        description: 'Fix a bug',
        impact: 'Application crash',
        confidence: 0.9,
        filePath: 'src/app.php',
        lineStart: 10,
        lineEnd: 15,
        currentCode: 'old code',
        replacementCode: 'new code',
        explanation: 'This is the explanation',
        references: ['https://example.com'],
    );

    expect($finding->filePath)->toBe('src/app.php');
    expect($finding->lineStart)->toBe(10);
    expect($finding->lineEnd)->toBe(15);
    expect($finding->currentCode)->toBe('old code');
    expect($finding->replacementCode)->toBe('new code');
    expect($finding->explanation)->toBe('This is the explanation');
    expect($finding->references)->toBe(['https://example.com']);
});

it('can be created from array', function (): void {
    $finding = ReviewFinding::fromArray([
        'severity' => 'high',
        'category' => 'security',
        'title' => 'Test Finding',
        'description' => 'Test description',
        'impact' => 'Test impact',
        'confidence' => 0.8,
    ]);

    expect($finding->severity)->toBe(SentinelConfigSeverity::High);
    expect($finding->category)->toBe(FindingCategory::Security);
    expect($finding->title)->toBe('Test Finding');
    expect($finding->confidence)->toBe(0.8);
});

it('uses default severity when invalid', function (): void {
    $finding = ReviewFinding::fromArray([
        'severity' => 'invalid',
        'category' => 'security',
        'title' => 'Test',
        'description' => 'Test',
        'impact' => '',
        'confidence' => 0.5,
    ]);

    expect($finding->severity)->toBe(SentinelConfigSeverity::Info);
});

it('uses default category when invalid', function (): void {
    $finding = ReviewFinding::fromArray([
        'severity' => 'high',
        'category' => 'invalid',
        'title' => 'Test',
        'description' => 'Test',
        'impact' => '',
        'confidence' => 0.5,
    ]);

    expect($finding->category)->toBe(FindingCategory::Maintainability);
});

it('checks if finding has location', function (): void {
    $withLocation = new ReviewFinding(
        severity: SentinelConfigSeverity::Low,
        category: FindingCategory::Style,
        title: 'Style Issue',
        description: 'Description',
        impact: 'Minor',
        confidence: 0.7,
        filePath: 'src/file.php',
    );

    $withoutLocation = new ReviewFinding(
        severity: SentinelConfigSeverity::Low,
        category: FindingCategory::Style,
        title: 'Style Issue',
        description: 'Description',
        impact: 'Minor',
        confidence: 0.7,
    );

    expect($withLocation->hasLocation())->toBeTrue();
    expect($withoutLocation->hasLocation())->toBeFalse();
});

it('checks if finding has suggestion', function (): void {
    $withSuggestion = new ReviewFinding(
        severity: SentinelConfigSeverity::Medium,
        category: FindingCategory::Maintainability,
        title: 'Refactor',
        description: 'Description',
        impact: 'Improvement',
        confidence: 0.8,
        currentCode: 'old',
        replacementCode: 'new',
    );

    $withoutSuggestion = new ReviewFinding(
        severity: SentinelConfigSeverity::Medium,
        category: FindingCategory::Maintainability,
        title: 'Refactor',
        description: 'Description',
        impact: 'Improvement',
        confidence: 0.8,
    );

    $partialSuggestion = new ReviewFinding(
        severity: SentinelConfigSeverity::Medium,
        category: FindingCategory::Maintainability,
        title: 'Refactor',
        description: 'Description',
        impact: 'Improvement',
        confidence: 0.8,
        currentCode: 'old',
    );

    expect($withSuggestion->hasSuggestion())->toBeTrue();
    expect($withoutSuggestion->hasSuggestion())->toBeFalse();
    expect($partialSuggestion->hasSuggestion())->toBeFalse();
});

it('converts to array with required fields', function (): void {
    $finding = new ReviewFinding(
        severity: SentinelConfigSeverity::High,
        category: FindingCategory::Security,
        title: 'Test',
        description: 'Description',
        impact: 'High',
        confidence: 0.9,
    );

    $array = $finding->toArray();

    expect($array)->toBe([
        'severity' => 'high',
        'category' => 'security',
        'title' => 'Test',
        'description' => 'Description',
        'impact' => 'High',
        'confidence' => 0.9,
    ]);
});

it('converts to array with optional fields', function (): void {
    $finding = new ReviewFinding(
        severity: SentinelConfigSeverity::High,
        category: FindingCategory::Security,
        title: 'Test',
        description: 'Description',
        impact: 'High',
        confidence: 0.9,
        filePath: 'src/app.php',
        lineStart: 10,
        lineEnd: 20,
        currentCode: 'old',
        replacementCode: 'new',
        explanation: 'Why',
        references: ['https://example.com'],
    );

    $array = $finding->toArray();

    expect($array['file_path'])->toBe('src/app.php');
    expect($array['line_start'])->toBe(10);
    expect($array['line_end'])->toBe(20);
    expect($array['current_code'])->toBe('old');
    expect($array['replacement_code'])->toBe('new');
    expect($array['explanation'])->toBe('Why');
    expect($array['references'])->toBe(['https://example.com']);
});

it('excludes null optional fields from array', function (): void {
    $finding = new ReviewFinding(
        severity: SentinelConfigSeverity::Low,
        category: FindingCategory::Documentation,
        title: 'Docs',
        description: 'Description',
        impact: 'Low',
        confidence: 0.5,
    );

    $array = $finding->toArray();

    expect($array)->not->toHaveKey('file_path');
    expect($array)->not->toHaveKey('line_start');
    expect($array)->not->toHaveKey('line_end');
    expect($array)->not->toHaveKey('current_code');
    expect($array)->not->toHaveKey('replacement_code');
    expect($array)->not->toHaveKey('explanation');
    expect($array)->not->toHaveKey('references');
});
