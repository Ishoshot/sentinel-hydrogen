<?php

declare(strict_types=1);

namespace App\Services\Context;

/**
 * Redacts sensitive data patterns from text and detects sensitive files.
 */
final readonly class SensitiveDataRedactor
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

        // Polar
        'polar_token' => '/polar_(?:live|test)_[a-zA-Z0-9]{24,}/i',

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
     * Files that should have their contents completely redacted.
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

    /**
     * Redact sensitive data patterns from text.
     */
    public function redact(string $text): string
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
     * Determine whether a path refers to a sensitive file.
     */
    public function isSensitiveFile(string $path): bool
    {
        $filename = basename($path);
        $lowercaseFilename = mb_strtolower($filename);

        foreach (self::SENSITIVE_FILES as $sensitiveFile) {
            if ($lowercaseFilename === mb_strtolower($sensitiveFile)) {
                return true;
            }
        }

        return str_starts_with($lowercaseFilename, '.env');
    }

    /**
     * Generate a redaction replacement string.
     */
    private function generateRedaction(string $type, string $original): string
    {
        $length = mb_strlen($original);
        $preview = mb_substr($original, 0, min(4, $length));

        return sprintf('[REDACTED:%s:%s***]', $type, $preview);
    }
}
