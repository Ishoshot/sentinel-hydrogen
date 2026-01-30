<?php

declare(strict_types=1);

use App\Enums\Commands\CommandType;
use App\Services\Commands\Parsers\CommandParser;
use App\Services\Commands\ValueObjects\ParsedCommand;

beforeEach(function (): void {
    $this->parser = new CommandParser();
});

describe('hasMention', function (): void {
    it('returns true when @sentinel is present', function (): void {
        expect($this->parser->hasMention('@sentinel explain something'))->toBeTrue();
        expect($this->parser->hasMention('Hey @sentinel can you help?'))->toBeTrue();
        expect($this->parser->hasMention('@SENTINEL analyze this'))->toBeTrue();
    });

    it('returns false when @sentinel is not present', function (): void {
        expect($this->parser->hasMention('no mention here'))->toBeFalse();
        expect($this->parser->hasMention('@someone else'))->toBeFalse();
        expect($this->parser->hasMention(''))->toBeFalse();
    });
});

describe('parse', function (): void {
    it('returns null when no @sentinel mention exists', function (): void {
        expect($this->parser->parse('just a regular comment'))->toBeNull();
        expect($this->parser->parse('@other mention'))->toBeNull();
    });

    it('defaults to explain command when only mention is present', function (): void {
        $result = $this->parser->parse('@sentinel');

        expect($result)->toBeInstanceOf(ParsedCommand::class);
        expect($result->found)->toBeTrue();
        expect($result->commandType)->toBe(CommandType::Explain);
        expect($result->query)->toBe('');
    });

    it('defaults to explain command when no command is specified', function (): void {
        $result = $this->parser->parse('@sentinel the is_active column on User model');

        expect($result->commandType)->toBe(CommandType::Explain);
        expect($result->query)->toBe('the is_active column on User model');
    });

    it('parses explain command', function (): void {
        $result = $this->parser->parse('@sentinel explain the authentication flow');

        expect($result->commandType)->toBe(CommandType::Explain);
        expect($result->query)->toBe('the authentication flow');
    });

    it('parses analyze command', function (): void {
        $result = $this->parser->parse('@sentinel analyze this code section');

        expect($result->commandType)->toBe(CommandType::Analyze);
        expect($result->query)->toBe('this code section');
    });

    it('parses analyse command (British spelling)', function (): void {
        $result = $this->parser->parse('@sentinel analyse this code section');

        expect($result->commandType)->toBe(CommandType::Analyze);
        expect($result->query)->toBe('this code section');
    });

    it('parses review command', function (): void {
        $result = $this->parser->parse('@sentinel review app/Models/User.php');

        expect($result->commandType)->toBe(CommandType::Review);
        expect($result->query)->toBe('app/Models/User.php');
    });

    it('parses summarize command', function (): void {
        $result = $this->parser->parse('@sentinel summarize the changes in this PR');

        expect($result->commandType)->toBe(CommandType::Summarize);
        expect($result->query)->toBe('the changes in this PR');
    });

    it('parses summarise command (British spelling)', function (): void {
        $result = $this->parser->parse('@sentinel summarise this PR');

        expect($result->commandType)->toBe(CommandType::Summarize);
        expect($result->query)->toBe('this PR');
    });

    it('parses summary command as summarize', function (): void {
        $result = $this->parser->parse('@sentinel summary of these changes');

        expect($result->commandType)->toBe(CommandType::Summarize);
        expect($result->query)->toBe('of these changes');
    });

    it('parses find command', function (): void {
        $result = $this->parser->parse('@sentinel find usages of CreateWorkspace');

        expect($result->commandType)->toBe(CommandType::Find);
        expect($result->query)->toBe('usages of CreateWorkspace');
    });

    it('parses search command as find', function (): void {
        $result = $this->parser->parse('@sentinel search for authentication logic');

        expect($result->commandType)->toBe(CommandType::Find);
        expect($result->query)->toBe('for authentication logic');
    });

    it('parses locate command as find', function (): void {
        $result = $this->parser->parse('@sentinel locate the error handler');

        expect($result->commandType)->toBe(CommandType::Find);
        expect($result->query)->toBe('the error handler');
    });

    it('is case insensitive for commands', function (): void {
        expect($this->parser->parse('@sentinel EXPLAIN something')->commandType)->toBe(CommandType::Explain);
        expect($this->parser->parse('@sentinel Analyze something')->commandType)->toBe(CommandType::Analyze);
        expect($this->parser->parse('@sentinel FIND something')->commandType)->toBe(CommandType::Find);
    });

    it('handles mention in middle of comment', function (): void {
        $result = $this->parser->parse('Hey team, @sentinel explain this function please');

        expect($result->found)->toBeTrue();
        expect($result->commandType)->toBe(CommandType::Explain);
        expect($result->query)->toBe('this function please');
    });
});

describe('context hints extraction', function (): void {
    describe('file paths', function (): void {
        it('extracts file paths with directory separators', function (): void {
            $result = $this->parser->parse('@sentinel explain app/Models/User.php');

            expect($result->contextHints->files)->toContain('app/Models/User.php');
        });

        it('extracts multiple file paths', function (): void {
            $result = $this->parser->parse('@sentinel review app/Models/User.php and app/Services/AuthService.php');

            expect($result->contextHints->files)->toContain('app/Models/User.php');
            expect($result->contextHints->files)->toContain('app/Services/AuthService.php');
        });

        it('extracts file paths with known extensions', function (): void {
            $result = $this->parser->parse('@sentinel explain User.php');

            expect($result->contextHints->files)->toContain('User.php');
        });

        it('extracts various file extensions', function (string $extension): void {
            $result = $this->parser->parse("@sentinel explain test.{$extension}");

            expect($result->contextHints->files)->toContain("test.{$extension}");
        })->with(['php', 'js', 'ts', 'tsx', 'jsx', 'vue', 'py', 'go', 'rs', 'java', 'json', 'yaml', 'yml', 'md']);

        it('extracts file paths in backticks', function (): void {
            $result = $this->parser->parse('@sentinel explain `app/Models/User.php`');

            expect($result->contextHints->files)->toContain('app/Models/User.php');
        });
    });

    describe('symbols', function (): void {
        it('extracts class names', function (): void {
            $result = $this->parser->parse('@sentinel explain the User class');

            expect($result->contextHints->symbols)->toContain('User');
        });

        it('extracts class method references', function (): void {
            $result = $this->parser->parse('@sentinel explain User::isActive');

            expect($result->contextHints->symbols)->toContain('User::isActive');
        });

        it('extracts function calls', function (): void {
            $result = $this->parser->parse('@sentinel explain createUser()');

            expect($result->contextHints->symbols)->toContain('createUser()');
        });

        it('extracts symbols in backticks', function (): void {
            $result = $this->parser->parse('@sentinel explain the `CreateWorkspace` action');

            expect($result->contextHints->symbols)->toContain('CreateWorkspace');
        });

        it('extracts multiple symbols', function (): void {
            $result = $this->parser->parse('@sentinel explain how User and Workspace relate');

            expect($result->contextHints->symbols)->toContain('User');
            expect($result->contextHints->symbols)->toContain('Workspace');
        });
    });

    describe('line numbers', function (): void {
        it('extracts "line X" format', function (): void {
            $result = $this->parser->parse('@sentinel explain line 42');

            expect($result->contextHints->lines[0]->start)->toBe(42);
            expect($result->contextHints->lines[0]->end)->toBeNull();
        });

        it('extracts "line #X" format', function (): void {
            $result = $this->parser->parse('@sentinel explain line #100');

            expect($result->contextHints->lines[0]->start)->toBe(100);
            expect($result->contextHints->lines[0]->end)->toBeNull();
        });

        it('extracts "LX" format', function (): void {
            $result = $this->parser->parse('@sentinel explain L50');

            expect($result->contextHints->lines[0]->start)->toBe(50);
            expect($result->contextHints->lines[0]->end)->toBeNull();
        });

        it('extracts "LX-LY" range format', function (): void {
            $result = $this->parser->parse('@sentinel explain L10-L20');

            expect($result->contextHints->lines[0]->start)->toBe(10);
            expect($result->contextHints->lines[0]->end)->toBe(20);
        });

        it('extracts "LX-Y" range format', function (): void {
            $result = $this->parser->parse('@sentinel explain L10-20');

            expect($result->contextHints->lines[0]->start)->toBe(10);
            expect($result->contextHints->lines[0]->end)->toBe(20);
        });

        it('extracts multiple line references', function (): void {
            $result = $this->parser->parse('@sentinel explain line 10 and line 20');

            expect($result->contextHints->lines)->toHaveCount(2);
            expect($result->contextHints->lines[0]->start)->toBe(10);
            expect($result->contextHints->lines[1]->start)->toBe(20);
        });
    });
});
