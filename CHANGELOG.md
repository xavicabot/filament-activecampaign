# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.1.4] - 2026-02-19

### Fixed

- Fix null placeholders being sent as literal strings to ActiveCampaign — when a dynamic placeholder like `{user.profile.language}` resolved to `null`, the template engine returned the placeholder literal instead of an empty string

## [v1.1.3] - 2026-02-17

### Fixed

- Fix unresolved placeholders being sent as literal strings to the AC API

## [v1.1.2] - 2026-02-10

### Fixed

- Handle API validation errors gracefully instead of throwing exceptions

## [v1.1.1] - 2026-02-10

### Fixed

- Handle API validation errors gracefully instead of throwing exceptions

## [v1.1.0] - 2026-01-31

### Added

- Programmatic tag creation via facade (`getOrCreateTag`)
- Tag creation from Filament UI

## [v1.0.0] - 2026-01-31

### Added

- Import/export automations
- Tag creation from Filament UI

## [v0.1.5] - 2026-01-22

### Added

- Import/export automations and test fixes

### Fixed

- Bug for non-authenticated user triggers

## [v0.1.4] - 2025-12-22

### Added

- Email data support for contact sync

## [v0.1.2] - 2025-12-22

### Added

- Email data support for contact sync

## [v0.1.1] - 2025-12-22

### Added

- Email data support for contact sync

## [v0.1.0] - 2025-12-09

### Added

- Initial release
- ActiveCampaign API v3 integration
- Event-driven automation system with `trigger()` and `triggerWithEmail()`
- Dynamic template system with `{user.*}`, `{ctx.*}`, `{now}`, `{now_date}` placeholders
- Filament resources for automations, logs, lists, tags, and fields
- Metadata sync via `activecampaign:sync-metadata` artisan command
- Execution plan builder with preview functionality
- Audit logging for automation executions
