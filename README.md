# Sentinel App

AI-powered code review platform that integrates directly into your development workflow to help teams maintain code quality, correctness, and security at scale.

## Overview

Sentinel reviews pull requests automatically, surfaces high-signal issues, and provides long-term insights into code health — all without disrupting how teams already work.

Designed for teams that value clarity, control, and trust, Sentinel combines thoughtful automation with enterprise-grade governance and analytics.

## Features

-   **Automated PR Reviews** — AI-powered analysis runs on every pull request
-   **High-Signal Feedback** — Focus on issues that matter, not noise
-   **Code Health Insights** — Track quality trends over time
-   **Seamless Integration** — Works with your existing Git workflow
-   **Enterprise Governance** — Fine-grained controls and audit trails
-   **Security Analysis** — Surface vulnerabilities before they ship

## Requirements

-   PHP 8.4+
-   PostgreSQL 15+
-   Node.js 22+
-   Composer 2.x

## Installation

```bash
# Clone the repository
git clone https://github.com/ishoshot/sentinel.git
cd sentinel

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build

# Start the server
php artisan serve
```

## Development

```bash
# Start development server
npm run dev

# Run tests
composer test

# Run full test suite with coverage
composer test-code-coverage

# Code formatting
composer lint

# Static analysis
composer test:types
```

## Testing

Sentinel uses [Pest](https://pestphp.com) for testing with the following commands:

| Command                       | Description                   |
| ----------------------------- | ----------------------------- |
| `composer test`               | Run all tests                 |
| `composer test:arch`          | Run architecture tests        |
| `composer test:types`         | Run static analysis (PHPStan) |
| `composer test:type-coverage` | Check type coverage           |
| `composer test:code-coverage` | Run tests with code coverage  |

## Security

If you discover a security vulnerability, please report it via email. All security issues will be addressed promptly.

## License

Sentinel is proprietary software. All rights reserved.
