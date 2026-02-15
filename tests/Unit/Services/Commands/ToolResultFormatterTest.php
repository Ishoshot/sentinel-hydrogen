<?php

declare(strict_types=1);

use App\Services\Commands\Tools\ToolResultFormatter;

it('returns content unchanged when within limit', function (): void {
    $formatter = new ToolResultFormatter;

    expect($formatter->truncate('Hello', 10))->toBe('Hello');
});

it('truncates content exceeding limit', function (): void {
    $formatter = new ToolResultFormatter;

    $result = $formatter->truncate('This is a long string', 10);

    expect($result)->toBe('This is...');
    expect(mb_strlen($result))->toBeLessThanOrEqual(10);
});

it('handles exact limit length', function (): void {
    $formatter = new ToolResultFormatter;

    expect($formatter->truncate('12345', 5))->toBe('12345');
});
