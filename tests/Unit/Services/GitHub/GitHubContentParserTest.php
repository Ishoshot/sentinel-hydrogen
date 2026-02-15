<?php

declare(strict_types=1);

use App\Services\GitHub\Parsers\GitHubContentParser;

it('returns string input as-is', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode('plain text content');

    expect($result)->toBe('plain text content');
});

it('returns null for non-string non-array input', function (): void {
    $parser = new GitHubContentParser;

    expect($parser->decode(123))->toBeNull();
    expect($parser->decode(null))->toBeNull();
    expect($parser->decode(true))->toBeNull();
    expect($parser->decode(12.5))->toBeNull();
});

it('returns null when array has no content key', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode(['encoding' => 'base64']);

    expect($result)->toBeNull();
});

it('returns null when content is not a string', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode(['content' => 123, 'encoding' => 'base64']);

    expect($result)->toBeNull();
});

it('decodes base64 encoded content', function (): void {
    $parser = new GitHubContentParser;
    $originalContent = 'Hello, World!';

    $result = $parser->decode([
        'content' => base64_encode($originalContent),
        'encoding' => 'base64',
    ]);

    expect($result)->toBe($originalContent);
});

it('decodes base64 content with newlines', function (): void {
    $parser = new GitHubContentParser;
    $originalContent = 'This is a longer piece of content that would be wrapped.';
    $encoded = chunk_split(base64_encode($originalContent), 76, "\n");

    $result = $parser->decode([
        'content' => $encoded,
        'encoding' => 'base64',
    ]);

    expect($result)->toBe($originalContent);
});

it('assumes base64 encoding when encoding is not specified', function (): void {
    $parser = new GitHubContentParser;
    $originalContent = 'default encoding test';

    $result = $parser->decode([
        'content' => base64_encode($originalContent),
    ]);

    expect($result)->toBe($originalContent);
});

it('returns content as-is for non-base64 encoding', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode([
        'content' => 'raw content here',
        'encoding' => 'utf-8',
    ]);

    expect($result)->toBe('raw content here');
});

it('returns null for invalid base64 content', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode([
        'content' => '!!!invalid-base64!!!',
        'encoding' => 'base64',
    ]);

    expect($result)->toBeNull();
});

it('handles empty string content with base64 encoding', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode([
        'content' => base64_encode(''),
        'encoding' => 'base64',
    ]);

    expect($result)->toBe('');
});

it('handles empty array response', function (): void {
    $parser = new GitHubContentParser;

    $result = $parser->decode([]);

    expect($result)->toBeNull();
});
