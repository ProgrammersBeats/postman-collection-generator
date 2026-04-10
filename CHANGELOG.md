# Changelog

All notable changes to `laravel-postman-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-XX-XX

### Added
- Initial release
- `postman:collection` artisan command with interactive prompts
- Bearer token authentication support with automatic token storage
- Sanctum integration with pre-request and post-response scripts
- Six grouping strategies: prefix, controller, resource, name, middleware, tag
- Full descriptive documentation mode with validation rules and PHPDoc
- Environment file generation
- Support for Laravel 10, 11, 12, and 13
- Automatic login/logout route detection
- Route parameter extraction and documentation
- Configurable route filtering (include/exclude patterns)
- Custom authentication middleware detection
