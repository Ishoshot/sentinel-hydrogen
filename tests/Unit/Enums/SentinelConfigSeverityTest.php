<?php

declare(strict_types=1);

use App\Enums\SentinelConfigSeverity;

it('returns all values', function (): void {
    $values = SentinelConfigSeverity::values();

    expect($values)->toBeArray()
        ->toContain('critical')
        ->toContain('high')
        ->toContain('medium')
        ->toContain('low')
        ->toContain('info');
});

it('returns correct priorities', function (): void {
    expect(SentinelConfigSeverity::Critical->priority())->toBe(5)
        ->and(SentinelConfigSeverity::High->priority())->toBe(4)
        ->and(SentinelConfigSeverity::Medium->priority())->toBe(3)
        ->and(SentinelConfigSeverity::Low->priority())->toBe(2)
        ->and(SentinelConfigSeverity::Info->priority())->toBe(1);
});

it('checks threshold comparison correctly', function (): void {
    // Critical meets all thresholds
    expect(SentinelConfigSeverity::Critical->meetsThreshold(SentinelConfigSeverity::Info))->toBeTrue()
        ->and(SentinelConfigSeverity::Critical->meetsThreshold(SentinelConfigSeverity::Critical))->toBeTrue();

    // High doesn't meet Critical threshold
    expect(SentinelConfigSeverity::High->meetsThreshold(SentinelConfigSeverity::Critical))->toBeFalse()
        ->and(SentinelConfigSeverity::High->meetsThreshold(SentinelConfigSeverity::High))->toBeTrue()
        ->and(SentinelConfigSeverity::High->meetsThreshold(SentinelConfigSeverity::Medium))->toBeTrue();

    // Info only meets Info threshold
    expect(SentinelConfigSeverity::Info->meetsThreshold(SentinelConfigSeverity::Low))->toBeFalse()
        ->and(SentinelConfigSeverity::Info->meetsThreshold(SentinelConfigSeverity::Info))->toBeTrue();
});
