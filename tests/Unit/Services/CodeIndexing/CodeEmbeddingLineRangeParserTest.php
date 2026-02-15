<?php

declare(strict_types=1);

use App\Services\CodeIndexing\Parsers\CodeEmbeddingChunkContentParser;
use App\Services\CodeIndexing\Parsers\CodeEmbeddingLineRangeParser;

beforeEach(function (): void {
    $this->contentParser = new CodeEmbeddingChunkContentParser;
    $this->parser = new CodeEmbeddingLineRangeParser($this->contentParser);
});

it('returns empty string when both line boundaries are null', function (): void {
    $content = "line1\nline2\nline3";

    expect($this->parser->extract($content, null, null))->toBe('');
});

it('returns empty string when start line is null', function (): void {
    $content = "line1\nline2\nline3";

    expect($this->parser->extract($content, null, 2))->toBe('');
});

it('returns empty string when end line is null', function (): void {
    $content = "line1\nline2\nline3";

    expect($this->parser->extract($content, 2, null))->toBe('');
});

it('extracts a specific line range using one-based indexing', function (): void {
    $content = "line1\nline2\nline3\nline4\nline5";

    $result = $this->parser->extract($content, 2, 4);

    expect($result)->toBe("line2\nline3\nline4");
});

it('extracts a single line when start equals end', function (): void {
    $content = "line1\nline2\nline3";

    $result = $this->parser->extract($content, 2, 2);

    expect($result)->toBe('line2');
});

it('extracts from the first line', function (): void {
    $content = "line1\nline2\nline3";

    $result = $this->parser->extract($content, 1, 2);

    expect($result)->toBe("line1\nline2");
});

it('extracts to the last line', function (): void {
    $content = "line1\nline2\nline3";

    $result = $this->parser->extract($content, 2, 3);

    expect($result)->toBe("line2\nline3");
});

it('extracts all lines when range covers entire content', function (): void {
    $content = "line1\nline2\nline3";

    $result = $this->parser->extract($content, 1, 3);

    expect($result)->toBe($content);
});

it('truncates extracted content exceeding max chunk size', function (): void {
    $longLine = str_repeat('x', 9000);
    $content = "short\n{$longLine}\nafter";

    $result = $this->parser->extract($content, 2, 2);

    expect($result)->toEndWith("\n... (truncated)")
        ->and(mb_strlen($result))->toBeLessThan(9000);
});

it('handles end line beyond content length gracefully', function (): void {
    $content = "line1\nline2\nline3";

    $result = $this->parser->extract($content, 2, 100);

    expect($result)->toBe("line2\nline3");
});
