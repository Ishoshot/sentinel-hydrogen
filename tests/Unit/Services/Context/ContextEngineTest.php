<?php

declare(strict_types=1);

use App\Services\Context\ContextBag;
use App\Services\Context\ContextEngine;
use App\Services\Context\Contracts\ContextCollector;
use App\Services\Context\Contracts\ContextFilter;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->engine = new ContextEngine;
});

it('registers a collector', function (): void {
    $collector = Mockery::mock(ContextCollector::class);
    $collector->shouldReceive('name')->andReturn('test_collector');

    $result = $this->engine->registerCollector($collector);

    expect($result)->toBe($this->engine)
        ->and($this->engine->getCollectorNames())->toContain('test_collector');
});

it('registers a filter', function (): void {
    $filter = Mockery::mock(ContextFilter::class);
    $filter->shouldReceive('name')->andReturn('test_filter');

    $result = $this->engine->registerFilter($filter);

    expect($result)->toBe($this->engine)
        ->and($this->engine->getFilterNames())->toContain('test_filter');
});

it('returns collector names', function (): void {
    $collector1 = Mockery::mock(ContextCollector::class);
    $collector1->shouldReceive('name')->andReturn('collector1');

    $collector2 = Mockery::mock(ContextCollector::class);
    $collector2->shouldReceive('name')->andReturn('collector2');

    $this->engine->registerCollector($collector1);
    $this->engine->registerCollector($collector2);

    expect($this->engine->getCollectorNames())->toBe(['collector1', 'collector2']);
});

it('returns filter names', function (): void {
    $filter1 = Mockery::mock(ContextFilter::class);
    $filter1->shouldReceive('name')->andReturn('filter1');

    $filter2 = Mockery::mock(ContextFilter::class);
    $filter2->shouldReceive('name')->andReturn('filter2');

    $this->engine->registerFilter($filter1);
    $this->engine->registerFilter($filter2);

    expect($this->engine->getFilterNames())->toBe(['filter1', 'filter2']);
});

it('builds context by running collectors and filters', function (): void {
    Log::spy();

    $collector = Mockery::mock(ContextCollector::class);
    $collector->shouldReceive('name')->andReturn('test_collector');
    $collector->shouldReceive('priority')->andReturn(100);
    $collector->shouldReceive('shouldCollect')->andReturn(true);
    $collector->shouldReceive('collect')->once();

    $filter = Mockery::mock(ContextFilter::class);
    $filter->shouldReceive('name')->andReturn('test_filter');
    $filter->shouldReceive('order')->andReturn(10);
    $filter->shouldReceive('filter')->once();

    $this->engine->registerCollector($collector);
    $this->engine->registerFilter($filter);

    $result = $this->engine->build([]);

    expect($result)->toBeInstanceOf(ContextBag::class);
});

it('skips collectors that should not collect', function (): void {
    Log::spy();

    $collector = Mockery::mock(ContextCollector::class);
    $collector->shouldReceive('name')->andReturn('skipped_collector');
    $collector->shouldReceive('priority')->andReturn(100);
    $collector->shouldReceive('shouldCollect')->andReturn(false);
    $collector->shouldNotReceive('collect');

    $this->engine->registerCollector($collector);

    $result = $this->engine->build([]);

    expect($result)->toBeInstanceOf(ContextBag::class);
});

it('runs collectors in priority order highest first', function (): void {
    Log::spy();

    $order = [];

    $highPriorityCollector = Mockery::mock(ContextCollector::class);
    $highPriorityCollector->shouldReceive('name')->andReturn('high_priority');
    $highPriorityCollector->shouldReceive('priority')->andReturn(100);
    $highPriorityCollector->shouldReceive('shouldCollect')->andReturn(true);
    $highPriorityCollector->shouldReceive('collect')->andReturnUsing(function () use (&$order): void {
        $order[] = 'high';
    });

    $lowPriorityCollector = Mockery::mock(ContextCollector::class);
    $lowPriorityCollector->shouldReceive('name')->andReturn('low_priority');
    $lowPriorityCollector->shouldReceive('priority')->andReturn(10);
    $lowPriorityCollector->shouldReceive('shouldCollect')->andReturn(true);
    $lowPriorityCollector->shouldReceive('collect')->andReturnUsing(function () use (&$order): void {
        $order[] = 'low';
    });

    // Register in reverse order
    $this->engine->registerCollector($lowPriorityCollector);
    $this->engine->registerCollector($highPriorityCollector);

    $this->engine->build([]);

    expect($order)->toBe(['high', 'low']);
});

it('runs filters in order lowest first', function (): void {
    Log::spy();

    $order = [];

    $lowOrderFilter = Mockery::mock(ContextFilter::class);
    $lowOrderFilter->shouldReceive('name')->andReturn('low_order');
    $lowOrderFilter->shouldReceive('order')->andReturn(10);
    $lowOrderFilter->shouldReceive('filter')->andReturnUsing(function () use (&$order): void {
        $order[] = 'low';
    });

    $highOrderFilter = Mockery::mock(ContextFilter::class);
    $highOrderFilter->shouldReceive('name')->andReturn('high_order');
    $highOrderFilter->shouldReceive('order')->andReturn(100);
    $highOrderFilter->shouldReceive('filter')->andReturnUsing(function () use (&$order): void {
        $order[] = 'high';
    });

    // Register in reverse order
    $this->engine->registerFilter($highOrderFilter);
    $this->engine->registerFilter($lowOrderFilter);

    $this->engine->build([]);

    expect($order)->toBe(['low', 'high']);
});

it('handles collector exceptions gracefully', function (): void {
    Log::spy();

    $collector = Mockery::mock(ContextCollector::class);
    $collector->shouldReceive('name')->andReturn('failing_collector');
    $collector->shouldReceive('priority')->andReturn(100);
    $collector->shouldReceive('shouldCollect')->andReturn(true);
    $collector->shouldReceive('collect')->andThrow(new RuntimeException('Collector failed'));

    $this->engine->registerCollector($collector);

    $result = $this->engine->build([]);

    expect($result)->toBeInstanceOf(ContextBag::class);

    Log::shouldHaveReceived('warning')
        ->with('Collector failed', Mockery::type('array'));
});

it('handles filter exceptions gracefully', function (): void {
    Log::spy();

    $filter = Mockery::mock(ContextFilter::class);
    $filter->shouldReceive('name')->andReturn('failing_filter');
    $filter->shouldReceive('order')->andReturn(10);
    $filter->shouldReceive('filter')->andThrow(new RuntimeException('Filter failed'));

    $this->engine->registerFilter($filter);

    $result = $this->engine->build([]);

    expect($result)->toBeInstanceOf(ContextBag::class);

    Log::shouldHaveReceived('warning')
        ->with('Filter failed', Mockery::type('array'));
});
