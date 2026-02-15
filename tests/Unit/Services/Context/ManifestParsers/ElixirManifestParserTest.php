<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\ManifestParsers\ElixirManifestParser;
use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

beforeEach(function (): void {
    $this->parser = new ElixirManifestParser(new ProjectManifestDependencyRegistry);
});

describe('parseMixExs', function (): void {
    it('returns empty result for empty content', function (): void {
        $result = $this->parser->parseMixExs('');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('returns empty result for content with no recognizable patterns', function (): void {
        $result = $this->parser->parseMixExs('some random content without elixir patterns');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('parses the elixir runtime version', function (): void {
        $content = <<<'MIX'
        defmodule MyApp.MixProject do
          use Mix.Project

          def project do
            [
              app: :my_app,
              elixir: "~> 1.14",
              start_permanent: Mix.env() == :prod,
              deps: deps()
            ]
          end
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['runtime'])->toBe(['name' => 'Elixir', 'version' => '~> 1.14']);
    });

    it('detects phoenix framework with version', function (): void {
        $content = <<<'MIX'
        defp deps do
          [
            {:phoenix, "~> 1.7.0"},
            {:phoenix_html, "~> 3.3"}
          ]
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['frameworks'])->toContain(['name' => 'Phoenix', 'version' => '~> 1.7.0']);
    });

    it('detects phoenix framework without explicit version pattern', function (): void {
        // Content mentions :phoenix but not in the {:phoenix, "version"} format
        $content = <<<'MIX'
        defmodule MyApp do
          # uses :phoenix via some other mechanism
          @phoenix_dep :phoenix
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['frameworks'])->toContain(['name' => 'Phoenix', 'version' => '*']);
    });

    it('parses dependencies with versions', function (): void {
        $content = <<<'MIX'
        defp deps do
          [
            {:phoenix, "~> 1.7.0"},
            {:ecto_sql, "~> 3.10"},
            {:postgrex, ">= 0.0.0"},
            {:jason, "~> 1.2"}
          ]
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['dependencies'])->toContain(['name' => 'phoenix', 'version' => '~> 1.7.0'])
            ->and($result['dependencies'])->toContain(['name' => 'ecto_sql', 'version' => '~> 3.10'])
            ->and($result['dependencies'])->toContain(['name' => 'postgrex', 'version' => '>= 0.0.0'])
            ->and($result['dependencies'])->toContain(['name' => 'jason', 'version' => '~> 1.2']);
    });

    it('parses a full mix.exs file with runtime, framework, and dependencies', function (): void {
        $content = <<<'MIX'
        defmodule MyApp.MixProject do
          use Mix.Project

          def project do
            [
              app: :my_app,
              version: "0.1.0",
              elixir: "~> 1.15",
              start_permanent: Mix.env() == :prod,
              deps: deps()
            ]
          end

          defp deps do
            [
              {:phoenix, "~> 1.7.10"},
              {:phoenix_ecto, "~> 4.4"},
              {:ecto_sql, "~> 3.10"},
              {:postgrex, ">= 0.0.0"},
              {:telemetry_metrics, "~> 0.6"},
              {:jason, "~> 1.2"},
              {:plug_cowboy, "~> 2.5"}
            ]
          end
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['runtime'])->toBe(['name' => 'Elixir', 'version' => '~> 1.15'])
            ->and($result['frameworks'])->toContain(['name' => 'Phoenix', 'version' => '~> 1.7.10'])
            ->and($result['dependencies'])->toHaveCount(7);
    });

    it('handles content with elixir version but no dependencies', function (): void {
        $content = <<<'MIX'
        defmodule MyApp.MixProject do
          def project do
            [
              app: :my_app,
              elixir: "~> 1.16"
            ]
          end
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['runtime'])->toBe(['name' => 'Elixir', 'version' => '~> 1.16'])
            ->and($result['frameworks'])->toBe([])
            ->and($result['dependencies'])->toBe([]);
    });

    it('handles content with dependencies but no runtime version', function (): void {
        $content = <<<'MIX'
        defp deps do
          [
            {:jason, "~> 1.2"},
            {:plug, "~> 1.14"}
          ]
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result)->not->toHaveKey('runtime')
            ->and($result['dependencies'])->toHaveCount(2);
    });

    it('handles dependencies with underscores in names', function (): void {
        $content = <<<'MIX'
        defp deps do
          [
            {:phoenix_live_view, "~> 0.20.0"},
            {:phoenix_live_dashboard, "~> 0.8.0"}
          ]
        end
        MIX;

        $result = $this->parser->parseMixExs($content);

        expect($result['dependencies'])->toContain(['name' => 'phoenix_live_view', 'version' => '~> 0.20.0'])
            ->and($result['dependencies'])->toContain(['name' => 'phoenix_live_dashboard', 'version' => '~> 0.8.0']);
    });
});
