# Changeslog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Fixed
- Target round validation in duplicate_question external function - detected by https://mdlshield.com
- MSSQL compatibility in backup SQL: `LIMIT 1` replaced with DB-family-aware `TOP 1` / `LIMIT 1` in `backup_kahoodle_stepslib.php` - detected by https://mdlshield.com
- Course settings page crashed with a `has_capability()` TypeError when the course used the Single activity format with a Kahoodle activity #5

## [1.1.0] - 2026-03-06

### Changed
- Requires tool_realtime version 2.1.0.
- Better error messages when tool_realtime is not enabled.
- Better notification when connection is lost.

### Added
- Allow guest users to participate.
