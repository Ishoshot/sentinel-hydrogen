<?php

declare(strict_types=1);

use App\Models\Run;
use App\Services\Reviews\Resolvers\RunAnnotationsConfigResolver;
use App\Services\Reviews\ValueObjects\AnnotationConfig;

it('resolves config from run policy snapshot', function (): void {
    $run = new Run;
    $run->policy_snapshot = [
        'annotations' => [
            'style' => 'comment',
            'post_threshold' => 'high',
            'grouped' => false,
            'include_suggestions' => false,
        ],
    ];

    $resolver = new RunAnnotationsConfigResolver;
    $result = $resolver->resolve($run);

    expect($result)->toBeInstanceOf(AnnotationConfig::class)
        ->and($result->style)->toBe('comment')
        ->and($result->postThreshold)->toBe('high')
        ->and($result->grouped)->toBeFalse()
        ->and($result->includeSuggestions)->toBeFalse();
});

it('returns defaults when policy snapshot is empty', function (): void {
    $run = new Run;
    $run->policy_snapshot = [];

    $resolver = new RunAnnotationsConfigResolver;
    $result = $resolver->resolve($run);

    expect($result)->toBeInstanceOf(AnnotationConfig::class)
        ->and($result->style)->toBe('review')
        ->and($result->postThreshold)->toBe('medium')
        ->and($result->grouped)->toBeTrue()
        ->and($result->includeSuggestions)->toBeTrue();
});

it('returns defaults when annotations key missing', function (): void {
    $run = new Run;
    $run->policy_snapshot = null;

    $resolver = new RunAnnotationsConfigResolver;
    $result = $resolver->resolve($run);

    expect($result)->toBeInstanceOf(AnnotationConfig::class)
        ->and($result->style)->toBe('review')
        ->and($result->postThreshold)->toBe('medium');
});
