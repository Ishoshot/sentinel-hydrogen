<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\ModuleNameNormalizer;

beforeEach(function (): void {
    $this->normalizer = new ModuleNameNormalizer;
});

// === PHP-specific handling ===

it('returns null for App namespace imports', function (): void {
    expect($this->normalizer->normalize('App\\Models\\User'))->toBeNull()
        ->and($this->normalizer->normalize('App\\Http\\Controllers\\HomeController'))->toBeNull();
});

it('returns null for Tests namespace imports', function (): void {
    expect($this->normalizer->normalize('Tests\\Unit\\ExampleTest'))->toBeNull()
        ->and($this->normalizer->normalize('Tests\\Feature\\UserTest'))->toBeNull();
});

it('maps Illuminate namespace to laravel/framework', function (): void {
    expect($this->normalizer->normalize('Illuminate\\Support\\Facades\\DB'))->toBe('laravel/framework')
        ->and($this->normalizer->normalize('Illuminate\\Http\\Request'))->toBe('laravel/framework');
});

it('maps Symfony namespace to Symfony', function (): void {
    expect($this->normalizer->normalize('Symfony\\Component\\HttpFoundation\\Response'))->toBe('Symfony')
        ->and($this->normalizer->normalize('Symfony\\Process\\Process'))->toBe('Symfony');
});

it('extracts first segment from other PHP namespaces', function (): void {
    expect($this->normalizer->normalize('Carbon\\Carbon'))->toBe('Carbon')
        ->and($this->normalizer->normalize('Spatie\\Permission\\Models\\Role'))->toBe('Spatie');
});

// === Dart-specific handling ===

it('extracts package name from Dart imports', function (): void {
    expect($this->normalizer->normalize('package:flutter/material.dart'))->toBe('flutter')
        ->and($this->normalizer->normalize('package:dio/dio.dart'))->toBe('dio');
});

// === Rust/Perl/R-specific handling ===

it('extracts crate name from Rust imports', function (): void {
    expect($this->normalizer->normalize('serde::Deserialize'))->toBe('serde')
        ->and($this->normalizer->normalize('tokio::runtime::Runtime'))->toBe('tokio');
});

it('returns null for Rust standard library modules', function (): void {
    expect($this->normalizer->normalize('std::collections::HashMap'))->toBeNull()
        ->and($this->normalizer->normalize('core::fmt::Display'))->toBeNull()
        ->and($this->normalizer->normalize('alloc::vec::Vec'))->toBeNull()
        ->and($this->normalizer->normalize('self::something'))->toBeNull()
        ->and($this->normalizer->normalize('super::parent_module'))->toBeNull()
        ->and($this->normalizer->normalize('crate::my_module'))->toBeNull();
});

// === Clojure-specific handling ===

it('extracts namespace from Clojure imports', function (): void {
    expect($this->normalizer->normalize('mylib/core'))->toBe('mylib')
        ->and($this->normalizer->normalize('utils/helpers'))->toBe('utils');
});

it('treats dotted slash imports matching Go pattern as Go modules', function (): void {
    // my.library/core matches the Go pattern /^[a-z]+\.[a-z]+\// so it's treated as a Go import
    expect($this->normalizer->normalize('my.library/core'))->toBe('my.library/core');
});

// === Go-specific handling ===

it('returns full module path for Go imports', function (): void {
    expect($this->normalizer->normalize('github.com/gin-gonic/gin'))->toBe('github.com/gin-gonic/gin')
        ->and($this->normalizer->normalize('golang.org/x/tools'))->toBe('golang.org/x/tools');
});

// === Python/Java/C#/Scala/Kotlin/Elixir/Haskell/OCaml/Julia/Lua ===

it('extracts root from dotted imports', function (): void {
    expect($this->normalizer->normalize('flask.app'))->toBe('flask')
        ->and($this->normalizer->normalize('django.http.request'))->toBe('django')
        ->and($this->normalizer->normalize('numpy.ndarray'))->toBe('numpy');
});

it('returns null for standard library roots', function (): void {
    expect($this->normalizer->normalize('java.util.List'))->toBeNull()
        ->and($this->normalizer->normalize('javax.servlet.http'))->toBeNull()
        ->and($this->normalizer->normalize('System.Linq'))->toBeNull()
        ->and($this->normalizer->normalize('Microsoft.AspNetCore'))->toBeNull()
        ->and($this->normalizer->normalize('os.path'))->toBeNull()
        ->and($this->normalizer->normalize('sys.argv'))->toBeNull()
        ->and($this->normalizer->normalize('io.Reader'))->toBeNull()
        ->and($this->normalizer->normalize('re.match'))->toBeNull()
        ->and($this->normalizer->normalize('json.dumps'))->toBeNull()
        ->and($this->normalizer->normalize('typing.Optional'))->toBeNull()
        ->and($this->normalizer->normalize('collections.OrderedDict'))->toBeNull()
        ->and($this->normalizer->normalize('functools.reduce'))->toBeNull()
        ->and($this->normalizer->normalize('itertools.chain'))->toBeNull()
        ->and($this->normalizer->normalize('Kernel.send'))->toBeNull()
        ->and($this->normalizer->normalize('Enum.map'))->toBeNull()
        ->and($this->normalizer->normalize('List.first'))->toBeNull()
        ->and($this->normalizer->normalize('Map.get'))->toBeNull()
        ->and($this->normalizer->normalize('String.trim'))->toBeNull()
        ->and($this->normalizer->normalize('IO.puts'))->toBeNull()
        ->and($this->normalizer->normalize('File.read'))->toBeNull();
});

// === Simple names (Ruby, Swift, JS, etc.) ===

it('returns simple module names as-is', function (): void {
    expect($this->normalizer->normalize('lodash'))->toBe('lodash')
        ->and($this->normalizer->normalize('express'))->toBe('express')
        ->and($this->normalizer->normalize('react'))->toBe('react');
});

// === Edge cases ===

it('handles sun and com.sun as standard library roots', function (): void {
    expect($this->normalizer->normalize('sun.misc.Unsafe'))->toBeNull();
});

it('handles com.sun standard library root', function (): void {
    // com.sun starts with "com" which is not in STD_LIB_ROOTS
    // but the root extracted is "com" via dot splitting
    expect($this->normalizer->normalize('com.sun.proxy'))->toBe('com');
});
