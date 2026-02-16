<?php

declare(strict_types=1);

use App\Services\CodeIndexing\Parsers\CodeEmbeddingChunkContentParser;

beforeEach(function (): void {
    $this->parser = new CodeEmbeddingChunkContentParser;
});

it('does not truncate content within limit', function (): void {
    $content = str_repeat('a', 100);

    expect($this->parser->truncate($content))->toBe($content);
});

it('does not truncate content exactly at max chunk size', function (): void {
    $content = str_repeat('a', 8000);

    expect($this->parser->truncate($content))->toBe($content);
});

it('truncates content exceeding max chunk size', function (): void {
    $content = str_repeat('a', 9000);

    $result = $this->parser->truncate($content);

    expect($result)->toEndWith("\n... (truncated)")
        ->and(mb_strlen($result))->toBeLessThan(9000);
});

it('preserves exactly 8000 characters before truncation suffix', function (): void {
    $content = str_repeat('x', 10000);

    $result = $this->parser->truncate($content);

    $expectedPrefix = str_repeat('x', 8000);
    expect($result)->toStartWith($expectedPrefix)
        ->and($result)->toBe($expectedPrefix."\n... (truncated)");
});

it('formats file chunks with path and content', function (): void {
    $result = $this->parser->formatFileChunk('src/Example.php', 'class Example {}');

    expect($result)->toBe("File: src/Example.php\n\nclass Example {}");
});

it('formats file chunks with empty content', function (): void {
    $result = $this->parser->formatFileChunk('src/Empty.php', '');

    expect($result)->toBe("File: src/Empty.php\n\n");
});

it('formats symbol chunks with type name file path and content', function (): void {
    $result = $this->parser->formatSymbolChunk('method', 'doWork', 'public function doWork() {}', 'src/Example.php');

    expect($result)->toBe('Method doWork in src/Example.php\n\npublic function doWork() {}');
});

it('capitalizes the symbol type in formatted output', function (): void {
    $result = $this->parser->formatSymbolChunk('class', 'MyService', 'class MyService {}', 'src/MyService.php');

    expect($result)->toStartWith('Class MyService in src/MyService.php');
});

it('formats function symbol chunks', function (): void {
    $result = $this->parser->formatSymbolChunk('function', 'helper', 'function helper() {}', 'src/helpers.php');

    expect($result)->toBe('Function helper in src/helpers.php\n\nfunction helper() {}');
});
