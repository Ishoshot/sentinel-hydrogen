<?php

declare(strict_types=1);

use App\Services\Context\SensitiveDataRedactor;

it('redacts api keys', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'api_key=sk-1234567890abcdefghij';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:api_key:');
    expect($result)->not->toContain('sk-1234567890abcdefghij');
});

it('redacts bearer tokens', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:bearer_token:');
});

it('redacts aws access keys', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'AKIAIOSFODNN7EXAMPLE';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:aws_access_key:');
});

it('redacts github tokens', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'Use ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx for access';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:github_token:');
});

it('redacts github pats', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'github_pat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:github_pat:');
});

it('redacts polar tokens', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'polar_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:polar_token:');
});

it('redacts database urls', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'mysql://user:password@localhost/database';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:db_url:');
});

it('redacts private keys', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = '-----BEGIN RSA PRIVATE KEY-----';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:private_key:');
});

it('redacts jwt tokens', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:jwt:');
});

it('redacts slack tokens', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'xoxb-FAKE-TOKEN-FOR-TESTING';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:slack_token:');
});

it('redacts sendgrid keys', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy';
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:sendgrid_key:');
});

it('does not modify safe text', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'This is a safe string with no sensitive data.';
    $result = $redactor->redact($text);

    expect($result)->toBe($text);
});

it('detects sensitive env files', function (): void {
    $redactor = new SensitiveDataRedactor();

    expect($redactor->isSensitiveFile('.env'))->toBeTrue();
    expect($redactor->isSensitiveFile('.env.local'))->toBeTrue();
    expect($redactor->isSensitiveFile('.env.production'))->toBeTrue();
    expect($redactor->isSensitiveFile('/path/to/.env'))->toBeTrue();
    expect($redactor->isSensitiveFile('.env.custom'))->toBeTrue();
});

it('detects sensitive credential files', function (): void {
    $redactor = new SensitiveDataRedactor();

    expect($redactor->isSensitiveFile('credentials.json'))->toBeTrue();
    expect($redactor->isSensitiveFile('service-account.json'))->toBeTrue();
    expect($redactor->isSensitiveFile('secrets.yaml'))->toBeTrue();
    expect($redactor->isSensitiveFile('secrets.yml'))->toBeTrue();
});

it('detects sensitive key files', function (): void {
    $redactor = new SensitiveDataRedactor();

    expect($redactor->isSensitiveFile('id_rsa'))->toBeTrue();
    expect($redactor->isSensitiveFile('id_ed25519'))->toBeTrue();
});

it('detects sensitive config files', function (): void {
    $redactor = new SensitiveDataRedactor();

    expect($redactor->isSensitiveFile('.npmrc'))->toBeTrue();
    expect($redactor->isSensitiveFile('.pypirc'))->toBeTrue();
    expect($redactor->isSensitiveFile('.htpasswd'))->toBeTrue();
});

it('does not flag regular files as sensitive', function (): void {
    $redactor = new SensitiveDataRedactor();

    expect($redactor->isSensitiveFile('app.php'))->toBeFalse();
    expect($redactor->isSensitiveFile('README.md'))->toBeFalse();
    expect($redactor->isSensitiveFile('config.json'))->toBeFalse();
    expect($redactor->isSensitiveFile('environment.ts'))->toBeFalse();
});

it('handles case insensitive file detection', function (): void {
    $redactor = new SensitiveDataRedactor();

    expect($redactor->isSensitiveFile('.ENV'))->toBeTrue();
    expect($redactor->isSensitiveFile('CREDENTIALS.JSON'))->toBeTrue();
    expect($redactor->isSensitiveFile('Secrets.yaml'))->toBeTrue();
});

it('includes preview in redaction', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = 'api_key=sk-1234567890abcdefghij';
    $result = $redactor->redact($text);

    expect($result)->toContain('***]');
});

it('redacts multiple patterns in same text', function (): void {
    $redactor = new SensitiveDataRedactor();

    $text = "api_key=sk-1234567890abcdefghij\npassword=supersecretpassword123";
    $result = $redactor->redact($text);

    expect($result)->toContain('[REDACTED:api_key:');
    expect($result)->toContain('[REDACTED:password_config:');
});
