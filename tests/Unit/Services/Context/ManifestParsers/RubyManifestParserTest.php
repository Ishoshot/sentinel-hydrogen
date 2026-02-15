<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\ManifestParsers\RubyManifestParser;
use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

beforeEach(function (): void {
    $this->parser = new RubyManifestParser(new ProjectManifestDependencyRegistry);
});

describe('parseGemfile', function (): void {
    it('returns empty result for empty content', function (): void {
        $result = $this->parser->parseGemfile('');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('returns empty result for content with no recognizable patterns', function (): void {
        $result = $this->parser->parseGemfile('# This is just a comment');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('parses ruby runtime version with double quotes', function (): void {
        $content = <<<'GEMFILE'
        ruby "3.2.2"

        gem "rails", "~> 7.1.0"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['runtime'])->toBe(['name' => 'Ruby', 'version' => '3.2.2']);
    });

    it('parses ruby runtime version with single quotes', function (): void {
        $content = <<<'GEMFILE'
        ruby '3.3.0'
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['runtime'])->toBe(['name' => 'Ruby', 'version' => '3.3.0']);
    });

    it('parses gems with version constraints', function (): void {
        $content = <<<'GEMFILE'
        gem "rails", "~> 7.1.0"
        gem "puma", ">= 5.0"
        gem "sqlite3", "~> 1.4"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['dependencies'])->toHaveCount(3);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('rails')
            ->and($names)->toContain('puma')
            ->and($names)->toContain('sqlite3');
    });

    it('parses gems without version constraints as wildcard', function (): void {
        $content = <<<'GEMFILE'
        gem "bootsnap"
        gem "jbuilder"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['dependencies'])->toContain(['name' => 'bootsnap', 'version' => '*'])
            ->and($result['dependencies'])->toContain(['name' => 'jbuilder', 'version' => '*']);
    });

    it('detects rails as a framework', function (): void {
        $content = <<<'GEMFILE'
        gem "rails", "~> 7.1.0"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['frameworks'])->toContain(['name' => 'Ruby on Rails', 'version' => '~> 7.1.0']);
        // Rails should also appear in dependencies
        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('rails');
    });

    it('detects sinatra as a framework', function (): void {
        $content = <<<'GEMFILE'
        gem "sinatra", "~> 3.1"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['frameworks'])->toContain(['name' => 'Sinatra', 'version' => '~> 3.1']);
    });

    it('detects hanami as a framework', function (): void {
        $content = <<<'GEMFILE'
        gem "hanami", "~> 2.1"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['frameworks'])->toContain(['name' => 'Hanami', 'version' => '~> 2.1']);
    });

    it('parses gems with single quotes', function (): void {
        $content = <<<'GEMFILE'
        gem 'pg', '~> 1.1'
        gem 'redis', '~> 5.0'
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('pg')
            ->and($names)->toContain('redis');
    });

    it('parses a full Gemfile with runtime, frameworks, and dependencies', function (): void {
        $content = <<<'GEMFILE'
        source "https://rubygems.org"
        git_source(:github) { |repo| "https://github.com/#{repo}.git" }

        ruby "3.2.2"

        gem "rails", "~> 7.1.2"
        gem "pg", "~> 1.1"
        gem "puma", ">= 5.0"
        gem "bootsnap"
        gem "redis", "~> 5.0"
        gem "sidekiq", "~> 7.2"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['runtime'])->toBe(['name' => 'Ruby', 'version' => '3.2.2'])
            ->and($result['frameworks'])->toContain(['name' => 'Ruby on Rails', 'version' => '~> 7.1.2'])
            ->and($result['dependencies'])->toHaveCount(6);
    });

    it('handles content with no runtime but has gems', function (): void {
        $content = <<<'GEMFILE'
        gem "sinatra", "~> 3.1"
        gem "thin"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result)->not->toHaveKey('runtime')
            ->and($result['frameworks'])->toContain(['name' => 'Sinatra', 'version' => '~> 3.1'])
            ->and($result['dependencies'])->toHaveCount(2);
    });

    it('handles gems with mixed quote styles', function (): void {
        $content = <<<'GEMFILE'
        gem "rails", '~> 7.0'
        gem 'puma', "~> 6.0"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('rails')
            ->and($names)->toContain('puma');
    });

    it('does not detect unknown gems as frameworks', function (): void {
        $content = <<<'GEMFILE'
        gem "pg", "~> 1.1"
        gem "redis", "~> 5.0"
        gem "sidekiq", "~> 7.2"
        GEMFILE;

        $result = $this->parser->parseGemfile($content);

        expect($result['frameworks'])->toBe([])
            ->and($result['dependencies'])->toHaveCount(3);
    });
});
