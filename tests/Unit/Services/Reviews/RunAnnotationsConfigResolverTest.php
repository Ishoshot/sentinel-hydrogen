<?php

declare(strict_types=1);

use App\Models\Run;
use App\Services\Reviews\Resolvers\RunAnnotationsConfigResolver;

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

    expect($result['style'])->toBe('comment');
    expect($result['post_threshold'])->toBe('high');
    expect($result['grouped'])->toBeFalse();
    expect($result['include_suggestions'])->toBeFalse();
});

it('returns defaults when policy snapshot is empty', function (): void {
    $run = new Run;
    $run->policy_snapshot = [];

    $resolver = new RunAnnotationsConfigResolver;
    $result = $resolver->resolve($run);

    expect($result['style'])->toBe('review');
    expect($result['post_threshold'])->toBe('medium');
    expect($result['grouped'])->toBeTrue();
    expect($result['include_suggestions'])->toBeTrue();
});

it('returns defaults when annotations key missing', function (): void {
    $run = new Run;
    $run->policy_snapshot = null;

    $resolver = new RunAnnotationsConfigResolver;
    $result = $resolver->resolve($run);

    expect($result['style'])->toBe('review');
    expect($result['post_threshold'])->toBe('medium');
});
