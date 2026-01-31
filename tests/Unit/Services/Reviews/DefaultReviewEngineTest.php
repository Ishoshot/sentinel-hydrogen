<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Reviews\DefaultReviewEngine;

beforeEach(function (): void {
    $this->engine = new DefaultReviewEngine;
});

it('returns a review result with summary, findings, and metrics', function (): void {
    $bag = new ContextBag(
        metrics: [
            'files_changed' => 5,
            'lines_added' => 100,
            'lines_deleted' => 50,
        ]
    );

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result)->toBeArray()
        ->toHaveKey('summary')
        ->toHaveKey('findings')
        ->toHaveKey('metrics');
});

it('returns empty findings by default', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['findings'])->toBeArray()->toBeEmpty();
});

it('returns low risk level by default', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['summary']['risk_level'])->toBe('low');
});

it('returns default overview message', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['summary']['overview'])->toBe('Review completed with no findings.');
});

it('returns empty recommendations by default', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['summary']['recommendations'])->toBeArray()->toBeEmpty();
});

it('includes correct metrics from context bag', function (): void {
    $bag = new ContextBag(
        metrics: [
            'files_changed' => 10,
            'lines_added' => 200,
            'lines_deleted' => 75,
        ]
    );

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['metrics']['files_changed'])->toBe(10)
        ->and($result['metrics']['lines_added'])->toBe(200)
        ->and($result['metrics']['lines_deleted'])->toBe(75);
});

it('uses zero for missing metrics', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['metrics']['files_changed'])->toBe(0)
        ->and($result['metrics']['lines_added'])->toBe(0)
        ->and($result['metrics']['lines_deleted'])->toBe(0);
});

it('returns rule-based model identifier', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['metrics']['model'])->toBe('rule-based')
        ->and($result['metrics']['provider'])->toBe('internal');
});

it('returns zero tokens used', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['metrics']['tokens_used_estimated'])->toBe(0);
});

it('returns zero duration', function (): void {
    $bag = new ContextBag;

    $context = [
        'policy_snapshot' => [],
        'context_bag' => $bag,
    ];

    $result = $this->engine->review($context);

    expect($result['metrics']['duration_ms'])->toBe(0);
});
