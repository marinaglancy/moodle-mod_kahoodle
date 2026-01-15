# Kahoodle - Real-time Quiz Activity Module for Moodle

## Overview

Kahoodle is a Moodle activity module that enables real-time, interactive quiz sessions where all participants take a quiz simultaneously. It's designed for engaging, game-like quiz experiences similar to popular classroom quiz platforms.

## Version Compatibility & Folder Structure

The plugin location varies depending on the Moodle version:

- **Moodle 4.5 and 5.0**: `mod/kahoodle/`
- **Moodle 5.1 and above**: `public/mod/kahoodle/`

**Current Development Environment**: Moodle 4.5.8+ (Build: 20260109)

To identify the current Moodle version, check the `version.php` file in the Moodle root directory (same location as `config-dist.php`).

## Directory Structure

```
mod/kahoodle/                  (or public/mod/kahoodle/ for 5.1+)
├── backup/
│   └── moodle2/              # Backup and restore functionality
│       ├── backup_kahoodle_activity_task.class.php
│       ├── backup_kahoodle_stepslib.php
│       ├── restore_kahoodle_activity_task.class.php
│       └── restore_kahoodle_stepslib.php
├── classes/
│   ├── constants.php         # Plugin constants (defaults, question types, stages, file areas)
│   ├── questions.php         # Question management API
│   ├── courseformat/         # Course format integration
│   │   └── overview.php
│   ├── event/                # Event observers and definitions
│   │   ├── course_module_viewed.php
│   │   └── course_module_instance_list_viewed.php
│   └── external/             # Web service definitions
│       └── add_questions.php # Batch question creation web service
├── db/
│   ├── access.php            # Capability definitions
│   └── install.xml           # Database schema
├── lang/
│   └── en/
│       └── kahoodle.php      # English language strings
├── pix/
│   └── monologo.svg          # Module icon
├── tests/
│   ├── behat/                # Behat acceptance tests
│   ├── external/             # Web service tests
│   │   └── add_questions_test.php
│   ├── generator/            # Test data generators
│   │   └── lib.php
│   ├── lib_test.php          # PHPUnit tests for lib.php
│   └── questions_test.php    # PHPUnit tests for questions API
├── index.php                 # List all instances in a course
├── lib.php                   # Core module functions
├── mod_form.php              # Activity settings form
├── version.php               # Plugin version information
└── view.php                  # Main view page
```

## Core Functionality

### Constants

All plugin constants are defined in `classes/constants.php`:

- **Default Values**: Activity defaults (lobby duration, question timing, points)
- **Question Types**: `QUESTION_TYPE_MULTICHOICE` (currently only type implemented)
- **Round Stages**: `STAGE_PREPARATION`, `STAGE_LOBBY`, `STAGE_QUESTION_PREVIEW`, `STAGE_QUESTION`, `STAGE_QUESTION_RESULTS`, `STAGE_LEADERS`, `STAGE_REVISION`, `STAGE_ARCHIVED`
- **File Areas**: `FILEAREA_QUESTION_IMAGE` for question images

### Activity Model

Each Kahoodle activity instance consists of:

1. **Activity Record**: Stored in the `kahoodle` table
2. **Course Module**: Linked via the core `course_modules` table
3. **Questions**: A list of questions, each with a type
   - **Multiple Choice** (most common): 2-8 answer options
   - Other question types (to be defined)

### Questions API

The `\mod_kahoodle\questions` class provides the core question management functionality:

#### Available Methods

1. **`get_editable_round_id(int $kahoodleid): ?int`**
   - Returns the ID of the editable round (preparation stage, not yet started)
   - Creates a new round if none exists
   - Returns null if the last round has already been started

2. **`add_question(\stdClass $questiondata): int`**
   - Adds a new question to the editable round
   - Creates question record, first version, and links to round
   - Supports file uploads via `imagedraftitemid` parameter
   - Throws exception if no editable round exists

3. **`edit_question(\stdClass $questiondata): void`**
   - Updates question content and/or behavior data
   - Creates new version if current version is used in started rounds
   - Otherwise updates existing version in-place
   - Throws exception if no editable round exists

4. **`delete_question(int $questionid): void`**
   - Removes question from editable round
   - Preserves version if used in started rounds
   - Deletes version and question if not used elsewhere
   - Throws exception if no editable round exists

#### Versioning Logic

- **Editable Round**: Questions in preparation-stage rounds (not yet started) can be freely edited
- **Started Rounds**: Once a round starts, its questions are "locked" at their current version
- **Smart Versioning**: When editing a question that's used in started rounds, a new version is created automatically
- **Historical Accuracy**: Past rounds always reference the exact version that was shown during gameplay

### Web Services

#### mod_kahoodle_add_questions

Batch question creation web service defined in `classes/external/add_questions.php`.

**Parameters:**
- Array of questions, each containing:
  - `kahoodleid` (required): Activity instance ID
  - `questiontext` (required): Question text
  - `questiontype` (optional): Defaults to multichoice
  - `questiontextformat` (optional): Defaults to FORMAT_HTML
  - `questionconfig` (optional): JSON for question-specific settings
  - `answersconfig` (optional): JSON for answers
  - `questionpreviewduration` (optional): Preview duration override
  - `questionduration` (optional): Question duration override
  - `questionresultsduration` (optional): Results duration override
  - `maxpoints` (optional): Maximum points override
  - `minpoints` (optional): Minimum points override
  - `imagedraftitemid` (optional): Draft file area ID for images

**Returns:**
- `questionids`: Array of created question IDs with their input array indices
- `warnings`: Array of errors for questions that failed to create

**Features:**
- Batch processing with individual error handling
- Validates context and permissions for each question
- Supports file uploads for question images
- Returns partial success (some questions may succeed while others fail)

### Round-Based Gameplay

The activity supports multiple rounds per instance. Each round follows this flow:

1. **Lobby Phase**:
   - Round starts with a "lobby" period
   - Participants (users from the core `user` table) register to join

2. **Question Phase**:
   - Questions are displayed one at a time
   - Participants submit answers
   - Points are awarded based on:
     - Answer correctness
     - Response speed (faster = more points)

3. **Scoreboard Display**:
   - After each question: Facilitator may show real-time scoreboard
   - Participants can view their own scores

4. **Round Completion**:
   - Final scores displayed
   - Leaderboard shown

### Data Persistence & History

- **Round Results**: Each round's results are stored permanently
- **Question Versioning**: Questions may be modified after a round finishes
- **Historical Accuracy**: When viewing past rounds, questions and answers are displayed as they appeared during that specific round (snapshot/versioning system required)

## Moodle Integration

### Standard Activity Features

The activity supports all standard Moodle activity features:

- **Group Mode**: Support for separate groups, visible groups, or no groups
- **Availability Conditions**: Access restrictions based on dates, grades, user fields, etc.
- **Completion Criteria**: Activity completion tracking
- **Grading**: Integration with Moodle gradebook
- **Backup & Restore**: Full backup and restore support
- **Events**: Proper event logging for reporting and analytics

### Development Constraints

**Allowed**:
- Use all Moodle core APIs
- Interact with core database tables (read-only for most)
- Use core libraries and functions
- Trigger and observe core events

**Not Allowed**:
- Modify files outside the plugin directory
- Use APIs from other plugins
- Direct modification of core database tables (except through proper APIs)

## Database Schema

The complete database schema is defined in `db/install.xml`. The schema uses a versioning system to track question changes over time while maintaining historical accuracy.

### Table Overview

1. **`kahoodle`**: Main activity instances with default settings
2. **`kahoodle_questions`**: Question base records (immutable properties)
3. **`kahoodle_question_versions`**: Version history for question content
4. **`kahoodle_rounds`**: Individual game rounds with stage tracking
5. **`kahoodle_round_questions`**: Questions used in specific rounds (with version snapshot and per-round settings)
6. **`kahoodle_participants`**: User participation with custom display names and avatars
7. **`kahoodle_responses`**: Individual user answers to questions

### Detailed Table Structure

#### 1. kahoodle (Activity Instances)
Main activity configuration table.

**Key Fields:**
- `allowrepeat`: Allow users to participate in multiple rounds
- `lobbyduration`: Default lobby duration (60s)
- `questionpreviewduration`: Default preview time (5s)
- `questionduration`: Default answer time (30s)
- `questionresultsduration`: Default results display time (10s)
- `defaultmaxpoints`: Maximum points for fastest correct answer (1000)
- `defaultminpoints`: Minimum points for slowest correct answer (500)

#### 2. kahoodle_questions (Question Base)
Stores immutable question properties. Each question has multiple versions.

**Key Fields:**
- `questiontype`: Type of question (multichoice, text, clickmap, etc.) - **immutable once created**
- `sortorder`: Display order in activity

#### 3. kahoodle_question_versions (Version History)
Stores all versions of question content. Questions can be edited, creating new versions.

**Key Fields:**
- `questionid`: FK to kahoodle_questions
- `version`: Version number (increments with each edit)
- `questiontext`: Question text content
- `questionconfig`: JSON for question-specific settings
- `answersconfig`: JSON for answer options, correct answers, etc.

**Unique Index:** `questionid, version` - ensures one version record per version number

**Usage:**
- When question is created: Insert version 1
- When question is edited: Insert new version with incremented number
- Rounds reference specific version IDs for historical accuracy

#### 4. kahoodle_rounds (Game Rounds)
Individual gameplay sessions.

**Key Fields:**
- `name`: Round title
- `currentstage`: Current stage (preparation, lobby, questionpreview, question, questionresults, leaders, revision, archived)
- `currentquestion`: Current question being displayed
- `stagestarttime`: When current stage started
- `timestarted`: When round lobby opened
- `timecompleted`: When round finished

#### 5. kahoodle_round_questions (Round-Specific Questions)
Links questions to rounds with per-round overrides and statistics.

**Key Fields:**
- `questionversionid`: FK to kahoodle_question_versions.id (specific version used)
- `sortorder`: Question order in this round (no unique constraint for easy reordering)
- `questionpreviewduration`: Override activity default (NULL = use default)
- `questionduration`: Override activity default (NULL = use default)
- `questionresultsduration`: Override activity default (NULL = use default)
- `maxpoints`: Override activity default (NULL = use default)
- `minpoints`: Override activity default (NULL = use default)
- `totalresponses`: Total responses collected (NULL until stats collected)
- `answerdistribution`: JSON with answer distribution (NULL until stats collected)

**Why per-round settings?** Facilitators can run "practice rounds" with more time or "speed rounds" with less time using the same questions.

#### 6. kahoodle_participants (Participants)
User participation in rounds with customization.

**Key Fields:**
- `userid`: FK to core user table
- `displayname`: Custom display name chosen by participant
- `avatar`: Avatar identifier/path
- `totalscore`: Total points earned in round
- `finalrank`: Final leaderboard position

**Unique Index:** `roundid, userid` - One participation per user per round (enforced at DB level)

#### 7. kahoodle_responses (User Answers)
Individual participant responses to questions.

**Key Fields:**
- `participantid`: FK to kahoodle_participants
- `roundquestionid`: FK to kahoodle_round_questions
- `response`: User's answer (JSON or text, flexible for different question types)
- `iscorrect`: 1 if correct, 0 if incorrect
- `points`: Points earned (considers answer speed)
- `responsetime`: Time taken in seconds (decimal precision)

**Unique Index:** `participantid, roundquestionid` - One response per participant per question

### Core Table References

The plugin references these Moodle core tables:
- **`user`**: Participant information
- **`course_modules`**: Activity instance in course
- **`course`**: Course information
- **`groups`**: Group mode support
- **`grade_items`**: Gradebook integration

## Key Implementation Considerations

### Question Versioning
Since questions can be modified after rounds are completed, implement a versioning or snapshot system to preserve the exact state of questions/answers at the time of each round.

### Real-time Functionality
Consider WebSocket or AJAX polling for:
- Lobby participant list updates
- Live scoreboard updates
- Question progression
- Timer synchronization

### Performance
- Efficient query handling for large participant groups
- Caching strategies for frequently accessed data
- Optimized scoreboard calculations

### Accessibility
- Full keyboard navigation support
- Screen reader compatibility
- Proper ARIA labels
- Color contrast compliance

### Mobile Responsiveness
- Touch-friendly interface for participants
- Responsive layouts for various screen sizes
- Consider mobile-first approach for participant view

## Development Workflow

### Version Number Management

After **every database schema change**, you must bump the version number in `version.php`:

**Rules:**
1. If the current version is NOT from today's date: Set version to `YYYYMMDD00` (today's date × 100)
2. If the current version IS from today's date: Increment by 1

**Example:**
- Current version: `2026011300` (January 13, 2026, version 00)
- After DB change: `2026011301` (same day, incremented)
- Tomorrow's first change: `2026011400` (January 14, 2026, version 00)

**Early Development Note:** During early development (when nobody is using the plugin yet), upgrade scripts in `db/upgrade.php` can be omitted. Once the plugin is in use, all database changes MUST include proper upgrade scripts.

### Git Workflow

**IMPORTANT**: This plugin has its own git repository separate from the Moodle repository.

When committing changes:
1. Change directory to the plugin folder first: `cd mod/kahoodle` (or `cd public/mod/kahoodle` for Moodle 5.1+)
2. Run git commands from within the plugin directory
3. This prevents accidentally committing to the Moodle repository

**Example:**
```bash
cd mod/kahoodle
git status
git add .
git commit -m "Add question management API"
git push
```

### Standard Workflow

1. **Testing Across Versions**: Test on Moodle 4.5, 5.0, 5.1, and 5.2 (when released)
2. **Database Changes**:
   - Modify `db/install.xml`
   - Bump version in `version.php`
   - In production: Create upgrade script in `db/upgrade.php`
3. **Capabilities**: Define in `db/access.php`
4. **Language Strings**: Add to `lang/en/kahoodle.php`
5. **Events**: Create event classes in `classes/event/`
6. **Testing**: Write PHPUnit tests and Behat scenarios

## Useful Moodle APIs

- **Database API**: `$DB->get_record()`, `$DB->insert_record()`, etc.
- **Capability API**: `require_capability()`, `has_capability()`
- **Event API**: Event triggering and observation
- **Form API**: `moodleform` for settings and user input
- **Output API**: Renderers and templates for UI
- **Gradebook API**: `grade_update()` for grade submission
- **Completion API**: Activity completion tracking
- **Group API**: `groups_get_activity_group()`, etc.

## License

This plugin follows Moodle's GNU GPL v3 or later license.

## Testing

### PHPUnit Tests

The plugin includes comprehensive PHPUnit test coverage:

#### Questions API Tests (`tests/questions_test.php`)
- 13 tests covering all question management methods
- Tests for round creation, question CRUD operations, versioning logic
- Edge cases: no editable rounds, permission checks, sort order
- All tests passing with 47 assertions

#### Web Service Tests (`tests/external/add_questions_test.php`)
- 8 tests for batch question creation web service
- Tests for single/multiple questions, permissions, error handling
- Mixed success scenarios, parameter validation
- All tests passing with 43 assertions

### Test Data Generator

The `mod_kahoodle_generator` (in `tests/generator/lib.php`) provides:
- `create_instance($record)`: Create kahoodle activity instances
- `create_question($record)`: Create questions with all parameters

### Running Tests

```bash
# Run all kahoodle tests
vendor/bin/phpunit mod/kahoodle/tests/

# Run specific test file
vendor/bin/phpunit mod/kahoodle/tests/questions_test.php
vendor/bin/phpunit mod/kahoodle/tests/external/add_questions_test.php

# Run with filter
vendor/bin/phpunit --filter questions_test
```

## Current Status

**Implemented:**
- Database schema with versioning system
- Question management API with smart versioning
- Batch question creation web service
- Comprehensive test coverage
- Constants for defaults, types, stages, file areas
- Test data generators

**In Progress:**
- User interface for question management
- Round gameplay mechanics
- Real-time functionality (WebSocket/polling)
- Participant and response tracking

**To Do:**
- Frontend UI components
- Scoreboard and leaderboard displays
- Mobile-responsive participant view
- Additional question types
- Behat acceptance tests
