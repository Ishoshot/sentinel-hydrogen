<?php

declare(strict_types=1);

use App\Enums\Briefings\BriefingDownloadSource;

it('returns all values', function (): void {
    $values = BriefingDownloadSource::values();

    expect($values)->toBeArray()
        ->toContain('dashboard')
        ->toContain('share_link')
        ->toContain('api')
        ->toContain('email')
        ->toContain('scheduled');
});

it('has correct cases', function (): void {
    expect(BriefingDownloadSource::cases())->toHaveCount(5);
    expect(BriefingDownloadSource::Dashboard->value)->toBe('dashboard');
    expect(BriefingDownloadSource::ShareLink->value)->toBe('share_link');
    expect(BriefingDownloadSource::Api->value)->toBe('api');
    expect(BriefingDownloadSource::Email->value)->toBe('email');
    expect(BriefingDownloadSource::Scheduled->value)->toBe('scheduled');
});
