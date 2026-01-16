# Changelog

All notable changes to `asset-cleaner` will be documented in this file.

## v1.3.0 - 2026-01-16

### Added

- Root config file scanning for asset references (`root_config_files` config option)
- Default scanning of `tailwind.config.js`, `vite.config.js`, and other build tool configs
- Prevents false positives for images referenced in Tailwind's `backgroundImage` config

**Full Changelog**: https://github.com/daikazu/asset-cleaner/compare/v1.2.1...v1.3.0

## v1.2.1 - 2026-01-15

### Fixed

- Allow scanning `resources/views/vendor` for asset references (previously excluded by `**/vendor/**` pattern)
- Require testbench ^10.1 to fix PHPUnit error handler bug in CI
- Simplified CI workflow (Ubuntu-only, removed Windows)

**Full Changelog**: https://github.com/daikazu/asset-cleaner/compare/v1.2.0...v1.2.1

## v1.2.0 - 2026-01-07

### What's Changed

* feat: scan and remove unused blade components by @daikazu in https://github.com/daikazu/asset-cleaner/pull/2

## v1.1.0 - 2026-01-05

Feature: Accounts for `blade-ui-kit/blade-icons` SVGs
Fix: Issue where multiple date-stamped backup folders were created.

**Full Changelog**: https://github.com/daikazu/asset-cleaner/compare/v1.0.0...v1.1.0

## v1.0.0 - 2026-01-03

Initial Release
