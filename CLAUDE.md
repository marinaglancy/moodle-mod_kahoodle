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
├── amd/
│   └── src/
│       ├── gamecontroller.js # AMD module for facilitator game control
│       ├── participant.js    # AMD module for participant interface
│       └── questions.js      # AMD module for question management UI
├── backup/
│   └── moodle2/              # Backup and restore functionality
│       ├── backup_kahoodle_activity_task.class.php
│       ├── backup_kahoodle_stepslib.php
│       ├── restore_kahoodle_activity_task.class.php
│       └── restore_kahoodle_stepslib.php
├── classes/
│   ├── api.php               # General API functions
│   ├── constants.php         # Plugin constants (defaults, stages, file areas, field lists)
│   ├── questions.php         # Question management API
│   ├── courseformat/         # Course format integration
│   │   └── overview.php
│   ├── event/                # Event observers and definitions
│   │   ├── course_module_viewed.php
│   │   ├── course_module_instance_list_viewed.php
│   │   ├── question_created.php
│   │   ├── question_updated.php
│   │   ├── question_removed.php
│   │   ├── round_created.php
│   │   ├── round_updated.php
│   │   ├── participant_joined.php
│   │   ├── participant_left.php
│   │   └── response_submitted.php
│   ├── external/             # Web service definitions
│   │   ├── add_questions.php # Batch question creation web service
│   │   ├── change_question_sortorder.php # Question reordering web service
│   │   ├── create_instance.php # Activity instance creation web service
│   │   ├── delete_question.php # Question deletion web service
│   │   └── preview_questions.php # Question preview web service
│   ├── form/                 # Dynamic forms
│   │   └── question.php      # Question add/edit modal form
│   ├── local/
│   │   ├── entities/         # Domain entity classes
│   │   │   ├── participant.php # Participant entity
│   │   │   ├── round.php     # Round entity with caching for kahoodle/cm/context
│   │   │   ├── round_question.php # Round question entity (joins 3 tables)
│   │   │   └── round_stage.php # Round stage entity for stage data export
│   │   ├── game/             # Game mechanics
│   │   │   ├── participants.php # Participant management (join, get)
│   │   │   ├── progress.php  # Game progress and stage transitions
│   │   │   └── realtime_channels.php # Realtime channel management
│   │   └── questiontypes/    # Question type implementations
│   │       ├── base.php      # Abstract base class for question types
│   │       └── multichoice.php # Multiple choice question type
│   ├── output/               # Output classes for templates
│   │   ├── landing.php       # Landing page output (stage-based view)
│   │   ├── renderer.php      # Plugin renderer
│   │   ├── results.php       # Results page output (all rounds with status)
│   │   └── roundquestion.php # Round question display output
│   └── reportbuilder/local/  # Report builder components
│       ├── entities/
│       │   └── question.php  # Question entity for reports
│       └── systemreports/
│           └── questions.php # Questions list system report
├── db/
│   ├── access.php            # Capability definitions
│   ├── install.xml           # Database schema
│   ├── services.php          # Web service definitions
│   └── upgrade.php           # Database upgrade scripts
├── lang/
│   └── en/
│       └── kahoodle.php      # English language strings
├── pix/
│   └── monologo.svg          # Module icon
├── templates/
│   ├── facilitator/          # Facilitator view templates
│   │   ├── common/           # Shared facilitator partials
│   │   │   ├── footer.mustache      # Footer with progress bar and controls
│   │   │   ├── leaderboard.mustache # Leaderboard with ranked participants
│   │   │   └── questionheader.mustache # Question header with number and text
│   │   ├── leaders.mustache  # Leaderboard display
│   │   ├── lobby.mustache    # Lobby waiting room
│   │   ├── preview.mustache  # Question preview stage
│   │   ├── question.mustache # Question display stage
│   │   ├── results.mustache  # Question results stage
│   │   └── revision.mustache # Revision/review stage (final leaderboard)
│   ├── participant/          # Participant view templates
│   │   ├── common/           # Shared participant partials
│   │   │   ├── base.mustache         # Base template for participant overlay
│   │   │   ├── participantinfo.mustache # Footer with avatar, name, score
│   │   │   └── questionheader.mustache  # Header with question pill and close button
│   │   ├── lobby.mustache    # Lobby waiting for participants
│   │   ├── preview.mustache  # Question preview for participants
│   │   ├── question.mustache # Question display for participants
│   │   ├── results.mustache  # Results display for participants
│   │   ├── revision.mustache # Revision stage for participants (final leaderboard)
│   │   └── waiting.mustache  # Waiting overlay for participants
│   ├── questiontypes/        # Question type display templates
│   │   └── multichoice/      # Multiple choice templates
│   │       ├── facilitator_question.mustache # Facilitator question view
│   │       ├── facilitator_results.mustache  # Facilitator results view
│   │       └── participant_question.mustache # Participant question view
│   ├── landing.mustache      # Landing page template (view.php)
│   └── results.mustache      # Results page template (all rounds)
├── tests/
│   ├── behat/                # Behat acceptance tests
│   ├── external/             # Web service tests
│   │   ├── add_questions_test.php
│   │   ├── change_question_sortorder_test.php
│   │   ├── create_instance_test.php
│   │   └── delete_question_test.php
│   ├── generator/            # Test data generators
│   │   ├── behat_mod_kahoodle_generator.php
│   │   └── lib.php
│   ├── api_test.php          # PHPUnit tests for api.php
│   └── questions_test.php    # PHPUnit tests for questions API
├── index.php                 # List all instances in a course
├── lib.php                   # Core module functions (includes inplace_editable callback)
├── mod_form.php              # Activity settings form
├── questions.php             # Question management page (accepts id or roundid)
├── results.php               # Results page showing all rounds
├── version.php               # Plugin version information
└── view.php                  # Main view page (landing page with actions)
```

## Core Functionality

### Constants

All plugin constants are defined in `classes/constants.php`:

- **Default Values**: Activity defaults (lobby duration, question timing, points)
- **Question Format**: `QUESTIONFORMAT_PLAIN` (0) for plain text with image, `QUESTIONFORMAT_RICHTEXT` (1) for rich text editor
- **Text Limits**: `QUESTIONTEXT_MAXLENGTH` (300 characters for plain text mode)
- **Round Stages**: `STAGE_PREPARATION`, `STAGE_LOBBY`, `STAGE_QUESTION_PREVIEW`, `STAGE_QUESTION`, `STAGE_QUESTION_RESULTS`, `STAGE_LEADERS`, `STAGE_REVISION`, `STAGE_ARCHIVED`
- **File Areas**: `FILEAREA_QUESTION_IMAGE` for question images
- **Field Lists**: `FIELDS_QUESTION_VERSION` and `FIELDS_ROUND_QUESTION` for consistent field handling across entities and API methods

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

1. **`get_question_types(): array`**
   - Returns array of available question type instances
   - Each instance extends `\mod_kahoodle\local\questiontypes\base`
   - Currently returns: `[multichoice]`

2. **`get_last_round(int $kahoodleid): round`**
   - Returns the most recent round, prioritizing rounds in preparation stage
   - Creates a new round if none exists
   - Returns a `round` entity instance

3. **`get_editable_round_id(int $kahoodleid): ?int`**
   - Returns the ID of the editable round (preparation stage, not yet started)
   - Creates a new round if none exists
   - Returns null if the last round has already been started

4. **`add_question(\stdClass $questiondata): round_question`**
   - Adds a new question to the editable round
   - Creates question record, first version, and links to round
   - Supports file uploads via `imagedraftitemid` parameter
   - Returns a `round_question` entity instance
   - Throws exception if no editable round exists

5. **`edit_question(round_question $roundquestion, \stdClass $questiondata): void`**
   - Updates question content and/or behavior data
   - Creates new version if current version is used in started rounds
   - Otherwise updates existing version in-place
   - Throws exception if no editable round exists

6. **`delete_question(round_question $roundquestion): void`**
   - Removes question from editable round
   - Preserves version if used in started rounds
   - Deletes version and question if not used elsewhere
   - Throws exception if no editable round exists

#### Versioning Logic

- **Editable Round**: Questions in preparation-stage rounds (not yet started) can be freely edited
- **Started Rounds**: Once a round starts, its questions are "locked" at their current version
- **Smart Versioning**: When editing a question that's used in started rounds, a new version is created automatically
- **Historical Accuracy**: Past rounds always reference the exact version that was shown during gameplay

### Entity Classes

The plugin uses entity classes in `classes/local/entities/` for domain object encapsulation:

#### round Entity (`round.php`)

Represents a game round with lazy-loaded cached access to related data.

**Factory Methods:**
- `create_from_id(int $id): self` - Load from database by ID
- `create_from_object(stdClass $record): self` - Create from existing record

**Methods:**
- `get_id(): int` - Get round ID
- `get_kahoodleid(): int` - Get parent activity ID
- `is_editable(): bool` - Check if round is in preparation stage
- `get_kahoodle(): stdClass` - Get cached kahoodle activity record
- `get_cm(): stdClass` - Get cached course module record
- `get_context(): context_module` - Get context module instance
- `get_name_inplace_editable(): inplace_editable` - Get inplace editable control for round name
- `update_name(string $name): inplace_editable` - Update round name and return inplace editable
- `duplicate(): self` - Create a new round by duplicating question configuration from this round

#### round_question Entity (`round_question.php`)

Represents a question in a round, joining data from 3 tables (kahoodle_round_questions, kahoodle_question_versions, kahoodle_questions) in a single query.

**Factory Methods:**
- `create_from_round_question_id(int $id): self` - Load by round question ID
- `create_from_question_id(int $id, ?round $round = null): self` - Load by question ID (uses last round if round not specified)
- `new_for_round_and_type(round $round, ?string $questiontype = null): self` - Create new instance for adding a question (doesn't persist to DB)

**Methods:**
- `get_id(): int` - Get round question ID (0 for new questions)
- `get_question_id(): int` - Get the question ID
- `get_round(): round` - Get cached round entity
- `get_data(): stdClass` - Get combined data from all joined tables
- `get_question_type(): base` - Get the question type instance for this question

### Question Types

Question types are implemented as classes in `classes/local/questiontypes/`. Each type extends the abstract `base` class.

#### Base Class (`base.php`)

Abstract class that all question types must extend:

**Required Methods:**
- `get_display_name(): string` - Human-readable name for UI
- `sanitize_question_config_data(round_question $roundquestion, \stdClass $data): void` - Type-specific data validation/sanitization
- `question_form_definition(round_question $roundquestion, \MoodleQuickForm $mform): void` - Add type-specific form elements
- `question_form_validation(round_question $roundquestion, array $data, array $files): array` - Type-specific form validation

**Provided Methods:**
- `get_type(): string` - Returns the type identifier (class name, e.g., 'multichoice')
- `sanitize_data(round_question $roundquestion, \stdClass $data): void` - Common sanitization (validates fields, formats, durations, points) then calls `sanitize_question_config_data()`

#### Multichoice Type (`multichoice.php`)

Multiple choice questions with 2-8 answer options.

**questionconfig Format:**
- One option per line
- Prefix correct answer with asterisk (`*`)
- Example: `"Option A\n*Option B\nOption C"` (Option B is correct)

**Validation:**
- Requires 2-8 options
- Exactly one option must be marked as correct

### Web Services

#### mod_kahoodle_add_questions

Batch question creation web service defined in `classes/external/add_questions.php`.

**Parameters:**
- Array of questions, each containing:
  - `kahoodleid` (required): Activity instance ID
  - `questiontext` (required): Question text
  - `questiontype` (optional): Defaults to multichoice
  - `questionconfig` (optional): Type-specific configuration (e.g., answer options for multichoice)
  - `questionpreviewduration` (optional): Preview duration override
  - `questionduration` (optional): Question duration override
  - `questionresultsduration` (optional): Results duration override
  - `maxpoints` (optional): Maximum points override
  - `minpoints` (optional): Minimum points override
  - `imagedraftitemid` (optional): Draft file area ID for images (used with plain text format)

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

### Events

The plugin triggers the following events for logging, reporting, and analytics:

#### Standard Activity Events
- **`course_module_viewed`**: Triggered when a user views the activity (view.php)
- **`course_module_instance_list_viewed`**: Triggered when viewing list of all instances in a course (index.php)

#### Question Management Events
- **`question_created`**: Triggered when a new question is added to a round
  - Context: Activity module context
  - Related: Question version ID, round ID, kahoodle ID

- **`question_updated`**: Triggered when a question is edited (new version created or existing version modified)
  - Context: Activity module context
  - Related: Question version ID, round ID, kahoodle ID

- **`question_removed`**: Triggered when a question is removed from a round
  - Context: Activity module context
  - Related: Question ID, round ID, kahoodle ID

#### Round Management Events
- **`round_created`**: Triggered when a new round is created (automatically or via duplication)
  - Context: Activity module context
  - Related: Round ID, kahoodle ID

- **`round_updated`**: Triggered when round properties are modified (e.g., name changed, stage advanced)
  - Context: Activity module context
  - Related: Round ID, kahoodle ID

#### Participant Events
- **`participant_joined`**: Triggered when a user joins a round
  - Context: Activity module context
  - Related: Participant ID, round ID, user ID

- **`participant_left`**: Triggered when a participant leaves a round (future feature)
  - Context: Activity module context
  - Related: Participant ID, round ID, user ID

#### Gameplay Events
- **`response_submitted`**: Triggered when a participant submits an answer to a question
  - Context: Activity module context
  - Related: Response ID, participant ID, round question ID

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
- `questionformat`: Question input format (0=plain text with image, 1=rich text editor)
- `allowrepeat`: Allow users to participate in multiple rounds
- `lobbyduration`: Default lobby duration (60s)
- `questionpreviewduration`: Default preview time (5s)
- `questionduration`: Default answer time (30s)
- `questionresultsduration`: Default results display time (10s)
- `maxpoints`: Maximum points for fastest correct answer (1000)
- `minpoints`: Minimum points for slowest correct answer (500)

**Question Format Modes:**
- **Plain text (0)**: Simple textarea (max 300 chars) + optional single image upload. Provides consistent display across devices.
- **Rich text (1)**: Standard Moodle editor with embedded images. More flexible but teachers manage their own layout.

#### 2. kahoodle_questions (Question Base)
Stores immutable question properties. Each question has multiple versions.

**Key Fields:**
- `questiontype`: Type of question (multichoice, text, clickmap, etc.) - **immutable once created**
- `timecreated`: Creation timestamp

**Note:** Sort order is stored per-round in `kahoodle_round_questions`, not in this table.

#### 3. kahoodle_question_versions (Version History)
Stores all versions of question content. Questions can be edited, creating new versions.

**Key Fields:**
- `questionid`: FK to kahoodle_questions
- `version`: Version number (increments with each edit)
- `questiontext`: Question text content (format determined by kahoodle.questionformat)
- `questionconfig`: JSON for question-specific settings (e.g., answer options for multichoice)
- `timecreated`, `timemodified`: Timestamps

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
- `timecreated`, `timemodified`: Timestamps

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

The plugin uses `tool_realtime` for real-time communication between clients and server.

#### Realtime Channel Architecture

```
Facilitator Channel (area='facilitator', itemid=roundid)
├── Facilitators subscribe here
├── Receives: stage changes, participant list updates
└── Sends: advance, get_current

Game Channel (area='game', itemid=roundid)
├── All participants subscribe here
├── Receives: stage changes (preview, question, results)
└── Sends: nothing (read-only for now)

Participant Channel (area='participant', itemid=participantid)
├── Individual participant subscribes here
├── Receives: personal results, points earned
└── Sends: get_current, answer (future)
```

#### Channel Details

**Facilitator Channel:**
- Used by teachers to control the game flow
- Actions: `advance` (go to next stage), `get_current` (get current stage data)
- Notifications include full stage data with template and template data

**Game Channel:**
- Broadcast channel for all participants in a round
- Read-only for participants - they cannot send events
- Used for common stage notifications (question preview, question, results)

**Participant Channel:**
- Individual channel for each participant (itemid = participant record ID)
- Used for participant-specific data (their results, points earned)
- Participants can send: `get_current` (get current waiting stage)

#### PHP Realtime Handler

The `mod_kahoodle_realtime_event_received()` function in `lib.php` handles all incoming realtime events:
- Validates channel and component
- Routes to appropriate handler based on area (facilitator, game, participant)
- Returns response data that is sent back to the requesting client

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

When the user asks to commit, check commits, diff, or perform any git operations in the plugin:
1. **First change directory** to the plugin folder: `cd mod/kahoodle` (or `cd public/mod/kahoodle` for Moodle 5.1+)
2. Then run git commands from within the plugin directory
3. This prevents accidentally operating on the Moodle repository instead of the plugin

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
- **Inplace Editable API**: `\core\output\inplace_editable` for inline editing with AJAX callback via `mod_kahoodle_inplace_editable()` in lib.php
- **Gradebook API**: `grade_update()` for grade submission
- **Completion API**: Activity completion tracking
- **Group API**: `groups_get_activity_group()`, etc.

## License

This plugin follows Moodle's GNU GPL v3 or later license.

## Testing

### PHPUnit Tests

The plugin includes comprehensive PHPUnit test coverage:

#### Questions API Tests (`tests/questions_test.php`)
- Tests covering all question management methods
- Tests for round creation, question CRUD operations, versioning logic
- Edge cases: no editable rounds, permission checks, sort order

#### Web Service Tests (`tests/external/add_questions_test.php`)
- Tests for batch question creation web service
- Tests for single/multiple questions, permissions, error handling
- Mixed success scenarios, parameter validation

**Total: 32 tests passing**

### Test Data Generator

The `mod_kahoodle_generator` (in `tests/generator/lib.php`) provides:
- `create_instance($record)`: Create kahoodle activity instances
- `create_question($record)`: Create questions with all parameters

### Running Tests

```bash
# Initialize PHPUnit environment (required after database schema changes)
php admin/tool/phpunit/cli/init.php

# Run all kahoodle tests (from Moodle root directory)
vendor/bin/phpunit mod/kahoodle/tests/questions_test.php

# Run specific test file
vendor/bin/phpunit mod/kahoodle/tests/questions_test.php
vendor/bin/phpunit mod/kahoodle/tests/external/add_questions_test.php

# Run with filter
vendor/bin/phpunit --filter questions_test
```

**Note:** If you see "Moodle PHPUnit environment was initialised for different version", run `php admin/tool/phpunit/cli/init.php` to reinitialize.

## Current Status

**Implemented:**
- Database schema with versioning system
- Question format modes (plain text with image OR rich text editor)
- Question management API with smart versioning
- Batch question creation web service
- Question deletion web service
- Entity classes for `round` and `round_question` with caching
- Questions management page with Report Builder system report
- Question add/edit modal form (dynamic form with conditional fields based on format)
- AMD module for question UI interactions
- Constants for defaults, types, stages, file areas, and field lists
- Comprehensive test coverage (32 tests)
- Test data generators
- Backup/restore support for all activity settings
- Event logging for all major actions (10 events: view, question CRUD, round management, participants, responses)
- Landing page with stage-based content display (view.php)
  - Shows different UI based on round stage and user capabilities
  - Control panel for facilitators (start button)
  - Waiting message for participants before activity starts
  - Join/Resume buttons when activity is in progress
  - View results button when activity has finished (requires viewresults capability)
  - Prepare new round button for facilitators (duplicates question config from last round)
- Results page (results.php)
  - Displays all rounds with status-based field visibility
  - Preparation rounds: status only
  - In progress rounds: status, date, lobby opened time
  - Revision/Completed rounds: all stats including duration, participants, scores
  - Inplace editable round names (for users with facilitate capability)
  - Edit questions button for each round (requires manage_questions capability)
  - View participants and View statistics buttons for completed rounds
- Participant workflow
  - Join action creates participant record
  - Real-time channels for game and participant communication
  - Participant waiting overlay during game stages
  - Facilitator lobby shows list of joined participants
- Facilitator leaderboard display with ranked participants
- Participant result screens (preview, question, results, revision)
  - Mobile-responsive design with 500px max-width container
  - Question header with question pill and close button
  - Participant info footer with avatar, name, and score
  - Interactive multichoice answer buttons (2-column grid)
  - Result feedback (correct/incorrect/timeout with icons)
  - Final leaderboard for revision stage

**In Progress:**
- Round gameplay mechanics
- Participant response submission and scoring

**To Do:**
- Participant answer submission integration with scoring
- Additional question types
- Behat acceptance tests
