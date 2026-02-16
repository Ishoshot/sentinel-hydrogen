<?php

declare(strict_types=1);

use App\Services\Context\Collectors\Support\ManifestParsers\PythonManifestParser;
use App\Services\Context\Collectors\Support\ProjectManifestDependencyRegistry;

beforeEach(function (): void {
    $this->parser = new PythonManifestParser(new ProjectManifestDependencyRegistry);
});

describe('parsePyprojectToml', function (): void {
    it('returns empty result for empty content', function (): void {
        $result = $this->parser->parsePyprojectToml('');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('returns empty result for content with no recognizable patterns', function (): void {
        $result = $this->parser->parsePyprojectToml('[tool.poetry]
name = "my-project"');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('parses python runtime version from requires-python', function (): void {
        $content = <<<'TOML'
        [project]
        name = "my-project"
        requires-python = ">=3.11"
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['runtime'])->toBe(['name' => 'Python', 'version' => '>=3.11']);
    });

    it('parses python runtime version case-insensitively', function (): void {
        $content = <<<'TOML'
        [project]
        Requires-Python = ">=3.10"
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['runtime'])->toBe(['name' => 'Python', 'version' => '>=3.10']);
    });

    it('parses dependencies from the dependencies array', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "requests>=2.28.0",
            "click>=8.0",
            "pydantic>=2.0,<3.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['dependencies'])->toHaveCount(3);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('requests')
            ->and($names)->toContain('click')
            ->and($names)->toContain('pydantic');
    });

    it('parses dependencies without version constraints as wildcard', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "requests",
            "click",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['dependencies'])->toContain(['name' => 'requests', 'version' => '*'])
            ->and($result['dependencies'])->toContain(['name' => 'click', 'version' => '*']);
    });

    it('detects django as a framework', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "django>=4.2",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['frameworks'])->toContain(['name' => 'Django', 'version' => '>=4.2']);
    });

    it('detects flask as a framework', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "flask>=3.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['frameworks'])->toContain(['name' => 'Flask', 'version' => '>=3.0']);
    });

    it('detects fastapi as a framework', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "fastapi>=0.100.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['frameworks'])->toContain(['name' => 'FastAPI', 'version' => '>=0.100.0']);
    });

    it('detects tornado as a framework', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "tornado>=6.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['frameworks'])->toContain(['name' => 'Tornado', 'version' => '>=6.0']);
    });

    it('parses a full pyproject.toml with runtime, frameworks, and dependencies', function (): void {
        $content = <<<'TOML'
        [project]
        name = "my-web-app"
        version = "1.0.0"
        requires-python = ">=3.12"
        dependencies = [
            "django>=5.0",
            "celery>=5.3.0",
            "redis>=5.0",
            "psycopg2-binary>=2.9",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['runtime'])->toBe(['name' => 'Python', 'version' => '>=3.12'])
            ->and($result['frameworks'])->toContain(['name' => 'Django', 'version' => '>=5.0'])
            ->and($result['dependencies'])->toHaveCount(4);
    });

    it('cannot fully parse dependencies with extras due to bracket conflict in regex', function (): void {
        // The `]` inside extras like `[security]` terminates the lazy `\[(.*?)\]` match early,
        // so only the portion before the first `]` is captured as the dependencies block.
        $content = <<<'TOML'
        [project]
        dependencies = [
            "requests[security]>=2.28.0",
            "uvicorn[standard]>=0.24.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        // Only a partial match is possible; the block is truncated at `[security]`
        expect($result['dependencies'])->toHaveCount(0);
    });

    it('handles dependencies with tilde version operator', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "requests~=2.28",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['dependencies'])->toHaveCount(1)
            ->and($result['dependencies'][0]['name'])->toBe('requests')
            ->and($result['dependencies'][0]['version'])->toBe('~=2.28');
    });

    it('handles dependencies with not-equal version operator', function (): void {
        $content = <<<'TOML'
        [project]
        dependencies = [
            "setuptools!=50.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result['dependencies'])->toHaveCount(1)
            ->and($result['dependencies'][0]['name'])->toBe('setuptools')
            ->and($result['dependencies'][0]['version'])->toBe('!=50.0');
    });

    it('returns no runtime when requires-python is missing', function (): void {
        $content = <<<'TOML'
        [project]
        name = "my-project"
        dependencies = [
            "requests>=2.28.0",
        ]
        TOML;

        $result = $this->parser->parsePyprojectToml($content);

        expect($result)->not->toHaveKey('runtime')
            ->and($result['dependencies'])->toHaveCount(1);
    });
});

describe('parseRequirementsTxt', function (): void {
    it('returns empty result for empty content', function (): void {
        $result = $this->parser->parseRequirementsTxt('');

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('parses simple pinned dependencies', function (): void {
        $content = <<<'TXT'
        requests==2.31.0
        click==8.1.7
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'])->toHaveCount(2)
            ->and($result['dependencies'])->toContain(['name' => 'requests', 'version' => '==2.31.0'])
            ->and($result['dependencies'])->toContain(['name' => 'click', 'version' => '==8.1.7']);
    });

    it('parses dependencies with >= constraint', function (): void {
        $content = <<<'TXT'
        django>=4.2
        celery>=5.3.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'])->toHaveCount(2);

        $django = collect($result['dependencies'])->firstWhere('name', 'django');
        expect($django['version'])->toBe('>=4.2');
    });

    it('parses dependencies without version constraints as wildcard', function (): void {
        $content = <<<'TXT'
        requests
        click
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'])->toContain(['name' => 'requests', 'version' => '*'])
            ->and($result['dependencies'])->toContain(['name' => 'click', 'version' => '*']);
    });

    it('skips empty lines', function (): void {
        $content = <<<'TXT'
        requests==2.31.0

        click==8.1.7

        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'])->toHaveCount(2);
    });

    it('skips comment lines', function (): void {
        $content = <<<'TXT'
        # This is a comment
        requests==2.31.0
        # Another comment
        click==8.1.7
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'])->toHaveCount(2);
    });

    it('skips lines starting with a dash (flags like -r, -e, etc)', function (): void {
        $content = <<<'TXT'
        -r base.txt
        -e git+https://github.com/example/repo.git#egg=example
        requests==2.31.0
        --index-url https://pypi.org/simple
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        // Only "requests" should be parsed; -r, -e, and --index-url lines are skipped
        expect($result['dependencies'])->toHaveCount(1)
            ->and($result['dependencies'][0]['name'])->toBe('requests');
    });

    it('detects django as a framework', function (): void {
        $content = <<<'TXT'
        django==5.0.1
        gunicorn==21.2.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['frameworks'])->toContain(['name' => 'Django', 'version' => '==5.0.1']);
    });

    it('detects flask as a framework', function (): void {
        $content = <<<'TXT'
        flask>=3.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['frameworks'])->toContain(['name' => 'Flask', 'version' => '>=3.0']);
    });

    it('detects fastapi as a framework', function (): void {
        $content = <<<'TXT'
        fastapi>=0.100.0
        uvicorn>=0.24.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['frameworks'])->toContain(['name' => 'FastAPI', 'version' => '>=0.100.0']);
    });

    it('handles dependencies with extras', function (): void {
        $content = <<<'TXT'
        requests[security]>=2.28.0
        uvicorn[standard]>=0.24.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'])->toHaveCount(2);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('requests')
            ->and($names)->toContain('uvicorn');
    });

    it('handles dependencies with tilde operator', function (): void {
        $content = <<<'TXT'
        requests~=2.28
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'][0])->toBe(['name' => 'requests', 'version' => '~=2.28']);
    });

    it('handles dependencies with not-equal operator', function (): void {
        $content = <<<'TXT'
        setuptools!=50.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'][0])->toBe(['name' => 'setuptools', 'version' => '!=50.0']);
    });

    it('handles dependencies with less-than operator', function (): void {
        $content = <<<'TXT'
        numpy<2.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['dependencies'][0])->toBe(['name' => 'numpy', 'version' => '<2.0']);
    });

    it('parses a full requirements.txt with mixed formats', function (): void {
        $content = <<<'TXT'
        # Core dependencies
        django==5.0.1
        celery>=5.3.0
        redis
        psycopg2-binary>=2.9

        # Testing
        -r requirements-test.txt
        pytest>=7.0
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result['frameworks'])->toContain(['name' => 'Django', 'version' => '==5.0.1'])
            ->and($result['dependencies'])->toHaveCount(5);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('django')
            ->and($names)->toContain('celery')
            ->and($names)->toContain('redis')
            ->and($names)->toContain('psycopg2-binary')
            ->and($names)->toContain('pytest');
    });

    it('handles only whitespace and comments', function (): void {
        $content = <<<'TXT'
        # Just comments here

        # Nothing to parse

        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        expect($result)->toBe([
            'frameworks' => [],
            'dependencies' => [],
        ]);
    });

    it('handles dependencies with hyphens and underscores in names', function (): void {
        $content = <<<'TXT'
        psycopg2-binary>=2.9
        python_dateutil>=2.8
        TXT;

        $result = $this->parser->parseRequirementsTxt($content);

        $names = array_column($result['dependencies'], 'name');
        expect($names)->toContain('psycopg2-binary')
            ->and($names)->toContain('python_dateutil');
    });
});
