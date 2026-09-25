# Changeslog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Support for Moodle 5.3

### Fixed
- The results chart could show a wrong number of answers for an option when a participant sent a modified answer (for example "01" instead of "1") - detected by https://mdlshield.com
- Participants could not join with a nickname of 7 or more characters in CJK and other multibyte scripts; nicknames of only spaces are no longer accepted - detected by https://mdlshield.com
- Restoring a backup made with user data without including user data restored all rounds and their questions instead of only the last round - detected by https://mdlshield.com
- Rounds restored in the middle of a game are now archived automatically
- Deleting a question that had been edited after an earlier round could fail on SQL Server - detected by https://mdlshield.com
- The privacy data export now includes the participants' avatar images - detected by https://mdlshield.com
- Joining a game could take minutes when the server with the users' profile pictures was slow or unreachable - detected by https://mdlshield.com
- Links in question texts now point to the right place after a course is restored or copied - detected by https://mdlshield.com
- Question images can now only be web images, and other files embedded in rich text questions are downloaded instead of opened in the browser - detected by https://mdlshield.com
- In the fully anonymous mode the activity views are logged as anonymous events, so the logs no longer reveal which participant is which user - detected by https://mdlshield.com

## [4.5.1] - 2026-05-05

### Fixed
- Fixed exception 'Undefined constant "EDITOR_UNLIMITED_FILES"'

## [4.5.0] - 2026-04-18

### Changed
- Default value of "Allow repeat participation" setting changed from disabled to enabled for new activities.

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
