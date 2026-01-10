<?php

declare(strict_types=1);

namespace App\Services\Context\Filters;

use App\Services\Context\ContextBag;
use App\Services\Context\Contracts\ContextFilter;
use Illuminate\Support\Facades\Log;

/**
 * Removes or redacts sensitive data from context.
 *
 * Identifies and redacts potential secrets, API keys, passwords, and other
 * sensitive information to prevent exposure to the LLM.
 */
final class SensitiveDataFilter implements ContextFilter
{
    /**
     * Patterns for detecting sensitive data.
     *
     * @var array<string, string>
     */
    private const array SENSITIVE_PATTERNS = [
        // API Keys and tokens (common formats)
        'api_key' => '/(?:api[_-]?key|apikey)\s*[=:]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i',
        'bearer_token' => '/Bearer\s+([a-zA-Z0-9_\-\.]{20,})/i',
        'auth_token' => '/(?:auth[_-]?token|token)\s*[=:]\s*["\']?([a-zA-Z0-9_\-]{20,})["\']?/i',

        // AWS
        'aws_access_key' => '/(?:AKIA|ABIA|ACCA|ASIA)[A-Z0-9]{16}/i',
        'aws_secret_key' => '/(?:aws[_-]?secret[_-]?(?:access[_-]?)?key)\s*[=:]\s*["\']?([a-zA-Z0-9\/+=]{40})["\']?/i',

        // GitHub
        'github_token' => '/gh[pousr]_[A-Za-z0-9_]{36,}/i',
        'github_pat' => '/github_pat_[A-Za-z0-9_]{22,}/i',

        // Stripe
        'stripe_key' => '/(?:sk|pk)_(?:live|test)_[a-zA-Z0-9]{24,}/i',

        // Database connection strings
        'db_url' => '/(?:mysql|postgres|mongodb|redis):\/\/[^@\s]+:[^@\s]+@[^\s]+/i',

        // Private keys
        'private_key' => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH )?PRIVATE KEY-----/i',

        // Passwords in config
        'password_config' => '/(?:password|passwd|pwd)\s*[=:]\s*["\']?([^\s"\']{8,})["\']?/i',

        // JWT tokens
        'jwt' => '/eyJ[a-zA-Z0-9_-]*\.eyJ[a-zA-Z0-9_-]*\.[a-zA-Z0-9_-]*/i',

        // Generic secrets
        'secret' => '/(?:secret|client[_-]?secret)\s*[=:]\s*["\']?([a-zA-Z0-9_\-]{16,})["\']?/i',

        // Slack tokens
        'slack_token' => '/xox[baprs]-[a-zA-Z0-9-]+/i',

        // SendGrid
        'sendgrid_key' => '/SG\.[a-zA-Z0-9_-]{22,}\.[a-zA-Z0-9_-]{43,}/i',

        // Twilio
        'twilio_key' => '/SK[a-f0-9]{32}/i',
    ];

    /**
     * Files that should have their patches completely redacted.
     *
     * @var array<string>
     */
    private const array SENSITIVE_FILES = [
        '.env',
        '.env.local',
        '.env.production',
        '.env.staging',
        '.env.development',
        'credentials.json',
        'service-account.json',
        'secrets.yaml',
        'secrets.yml',
        '.npmrc',
        '.pypirc',
        'id_rsa',
        'id_ed25519',
        '.htpasswd',
    ];

    public function name(): string
    {
        return 'sensitive_data';
    }

    public function order(): int
    {
        return 30; // Run early, after path filters but before token limits
    }

    public function filter(ContextBag $bag): void
    {
        $redactedCount = 0;

        // Filter file patches
        $bag->files = array_map(function (array $file) use (&$redactedCount): array {
            $filename = basename($file['filename']);

            // Completely redact sensitive files
            if ($this->isSensitiveFile($filename)) {
                if ($file['patch'] !== null) {
                    $file['patch'] = '[REDACTED - sensitive file]';
                    $redactedCount++;
                }

                return $file;
            }

            // Redact sensitive patterns in patches
            if ($file['patch'] !== null) {
                $original = $file['patch'];
                $file['patch'] = $this->redactSensitiveData($file['patch']);

                if ($original !== $file['patch']) {
                    $redactedCount++;
                }
            }

            return $file;
        }, $bag->files);

        // Filter PR body in pullRequest data
        if (isset($bag->pullRequest['body']) && is_string($bag->pullRequest['body'])) {
            $original = $bag->pullRequest['body'];
            $bag->pullRequest['body'] = $this->redactSensitiveData($bag->pullRequest['body']);

            if ($original !== $bag->pullRequest['body']) {
                $redactedCount++;
            }
        }

        // Filter linked issue bodies and comments
        $bag->linkedIssues = array_map(function (array $issue) use (&$redactedCount): array {
            if ($issue['body'] !== null) {
                $original = $issue['body'];
                $issue['body'] = $this->redactSensitiveData($issue['body']);

                if ($original !== $issue['body']) {
                    $redactedCount++;
                }
            }

            $issue['comments'] = array_map(function (array $comment) use (&$redactedCount): array {
                $original = $comment['body'];
                $comment['body'] = $this->redactSensitiveData($comment['body']);

                if ($original !== $comment['body']) {
                    $redactedCount++;
                }

                return $comment;
            }, $issue['comments']);

            return $issue;
        }, $bag->linkedIssues);

        // Filter PR comments
        $bag->prComments = array_map(function (array $comment) use (&$redactedCount): array {
            $original = $comment['body'];
            $comment['body'] = $this->redactSensitiveData($comment['body']);

            if ($original !== $comment['body']) {
                $redactedCount++;
            }

            return $comment;
        }, $bag->prComments);

        if ($redactedCount > 0) {
            Log::info('SensitiveDataFilter: Redacted sensitive data', [
                'redacted_count' => $redactedCount,
            ]);
        }
    }

    /**
     * Check if a filename indicates a sensitive file.
     */
    private function isSensitiveFile(string $filename): bool
    {
        $lowercaseFilename = mb_strtolower($filename);

        foreach (self::SENSITIVE_FILES as $sensitiveFile) {
            if ($lowercaseFilename === mb_strtolower($sensitiveFile)) {
                return true;
            }
        }

        // Check for .env variants
        if (str_starts_with($lowercaseFilename, '.env')) {
            return true;
        }

        return false;
    }

    /**
     * Redact sensitive data patterns from text.
     */
    private function redactSensitiveData(string $text): string
    {
        foreach (self::SENSITIVE_PATTERNS as $name => $pattern) {
            $text = (string) preg_replace_callback(
                $pattern,
                fn (array $matches): string => $this->generateRedaction($name, $matches[0]),
                $text
            );
        }

        return $text;
    }

    /**
     * Generate a redaction replacement string.
     */
    private function generateRedaction(string $type, string $original): string
    {
        // Preserve the structure but redact the value
        $length = mb_strlen($original);
        $preview = mb_substr($original, 0, min(4, $length));

        return "[REDACTED:{$type}:{$preview}***]";
    }
}
