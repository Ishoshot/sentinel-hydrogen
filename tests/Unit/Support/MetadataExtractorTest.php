<?php

declare(strict_types=1);

use App\Support\MetadataExtractor;

it('can be created from array', function (): void {
    $extractor = MetadataExtractor::from(['key' => 'value']);

    expect($extractor)->toBeInstanceOf(MetadataExtractor::class);
});

it('extracts string with default', function (): void {
    $extractor = MetadataExtractor::from(['name' => 'test']);

    expect($extractor->string('name'))->toBe('test');
    expect($extractor->string('missing'))->toBe('');
    expect($extractor->string('missing', 'default'))->toBe('default');
});

it('returns default when value is not string', function (): void {
    $extractor = MetadataExtractor::from(['number' => 123, 'array' => []]);

    expect($extractor->string('number'))->toBe('');
    expect($extractor->string('array', 'fallback'))->toBe('fallback');
});

it('extracts string or null', function (): void {
    $extractor = MetadataExtractor::from(['name' => 'test', 'empty' => null]);

    expect($extractor->stringOrNull('name'))->toBe('test');
    expect($extractor->stringOrNull('missing'))->toBeNull();
    expect($extractor->stringOrNull('empty'))->toBeNull();
});

it('returns null when value is not string for stringOrNull', function (): void {
    $extractor = MetadataExtractor::from(['number' => 123]);

    expect($extractor->stringOrNull('number'))->toBeNull();
});

it('extracts integer with default', function (): void {
    $extractor = MetadataExtractor::from(['count' => 42, 'string_num' => '99']);

    expect($extractor->int('count'))->toBe(42);
    expect($extractor->int('string_num'))->toBe(99);
    expect($extractor->int('missing'))->toBe(0);
    expect($extractor->int('missing', 10))->toBe(10);
});

it('returns default when value is not numeric for int', function (): void {
    $extractor = MetadataExtractor::from(['text' => 'hello']);

    expect($extractor->int('text'))->toBe(0);
    expect($extractor->int('text', 5))->toBe(5);
});

it('extracts boolean with default', function (): void {
    $extractor = MetadataExtractor::from(['active' => true, 'disabled' => false]);

    expect($extractor->bool('active'))->toBeTrue();
    expect($extractor->bool('disabled'))->toBeFalse();
    expect($extractor->bool('missing'))->toBeFalse();
    expect($extractor->bool('missing', true))->toBeTrue();
});

it('returns default when value is not bool', function (): void {
    $extractor = MetadataExtractor::from(['string' => 'true', 'number' => 1]);

    expect($extractor->bool('string'))->toBeFalse();
    expect($extractor->bool('number', true))->toBeTrue();
});

it('extracts array with default', function (): void {
    $extractor = MetadataExtractor::from(['items' => [1, 2, 3]]);

    expect($extractor->array('items'))->toBe([1, 2, 3]);
    expect($extractor->array('missing'))->toBe([]);
    expect($extractor->array('missing', ['default']))->toBe(['default']);
});

it('returns default when value is not array', function (): void {
    $extractor = MetadataExtractor::from(['string' => 'not array']);

    expect($extractor->array('string'))->toBe([]);
    expect($extractor->array('string', ['fallback']))->toBe(['fallback']);
});

it('extracts author from array', function (): void {
    $extractor = MetadataExtractor::from([
        'author' => [
            'login' => 'johndoe',
            'avatar_url' => 'https://avatar.url',
        ],
    ]);

    $author = $extractor->author();

    expect($author)->toBe([
        'login' => 'johndoe',
        'avatar_url' => 'https://avatar.url',
    ]);
});

it('extracts author with fallback to sender_login', function (): void {
    $extractor = MetadataExtractor::from([
        'sender_login' => 'fallback_user',
    ]);

    $author = $extractor->author();

    expect($author)->toBe([
        'login' => 'fallback_user',
        'avatar_url' => null,
    ]);
});

it('extracts author with custom keys', function (): void {
    $extractor = MetadataExtractor::from([
        'custom_author' => ['login' => 'custom', 'avatar_url' => null],
        'custom_sender' => 'sender',
    ]);

    $author = $extractor->author('custom_author', 'custom_sender');

    expect($author['login'])->toBe('custom');
});

it('handles invalid author data', function (): void {
    $extractor = MetadataExtractor::from([
        'author' => 'not an array',
        'sender_login' => 'fallback',
    ]);

    $author = $extractor->author();

    expect($author['login'])->toBe('fallback');
});

it('extracts users array', function (): void {
    $extractor = MetadataExtractor::from([
        'assignees' => [
            ['login' => 'user1', 'avatar_url' => 'https://u1.avatar'],
            ['login' => 'user2', 'avatar_url' => null],
        ],
    ]);

    $users = $extractor->users('assignees');

    expect($users)->toHaveCount(2);
    expect($users[0])->toBe(['login' => 'user1', 'avatar_url' => 'https://u1.avatar']);
    expect($users[1])->toBe(['login' => 'user2', 'avatar_url' => null]);
});

it('returns empty array for missing users', function (): void {
    $extractor = MetadataExtractor::from([]);

    expect($extractor->users('assignees'))->toBe([]);
});

it('filters invalid users from array', function (): void {
    $extractor = MetadataExtractor::from([
        'users' => [
            ['login' => 'valid'],
            'invalid',
            ['no_login' => 'missing'],
            ['login' => 123],
        ],
    ]);

    $users = $extractor->users('users');

    expect($users)->toHaveCount(1);
    expect($users[0]['login'])->toBe('valid');
});

it('extracts labels array', function (): void {
    $extractor = MetadataExtractor::from([
        'labels' => [
            ['name' => 'bug', 'color' => 'ff0000'],
            ['name' => 'enhancement'],
        ],
    ]);

    $labels = $extractor->labels();

    expect($labels)->toHaveCount(2);
    expect($labels[0])->toBe(['name' => 'bug', 'color' => 'ff0000']);
    expect($labels[1])->toBe(['name' => 'enhancement', 'color' => 'cccccc']);
});

it('returns empty array for missing labels', function (): void {
    $extractor = MetadataExtractor::from([]);

    expect($extractor->labels())->toBe([]);
});

it('filters invalid labels from array', function (): void {
    $extractor = MetadataExtractor::from([
        'labels' => [
            ['name' => 'valid'],
            'invalid',
            ['no_name' => 'missing'],
            ['name' => 123],
        ],
    ]);

    $labels = $extractor->labels();

    expect($labels)->toHaveCount(1);
    expect($labels[0]['name'])->toBe('valid');
});

it('extracts labels with custom key', function (): void {
    $extractor = MetadataExtractor::from([
        'custom_labels' => [
            ['name' => 'custom', 'color' => '00ff00'],
        ],
    ]);

    $labels = $extractor->labels('custom_labels');

    expect($labels)->toHaveCount(1);
    expect($labels[0]['name'])->toBe('custom');
});
