# Kahoodle - Real-time Quiz Activity Module for Moodle

> **Local overrides:** If `CLAUDE.local.md` exists in this directory, read it for environment-specific configuration (Docker paths, tool locations, local dev environment details).

## Overview

Kahoodle is a Moodle activity module that enables real-time, interactive quiz sessions where all participants take a quiz simultaneously. It's designed for engaging, game-like quiz experiences similar to popular classroom quiz platforms.

## Version Compatibility & Folder Structure

The plugin location varies depending on the Moodle version:

- **Moodle 4.5 and 5.0**: `mod/kahoodle/`
- **Moodle 5.1 and above**: `public/mod/kahoodle/`

To identify the current Moodle version, check the `version.php` file in the Moodle root directory (same location as `config-dist.php`).

## Directory Structure

```
mod/kahoodle/                  (or public/mod/kahoodle/ for 5.1+)
├── amd/
│   └── src/
│       ├── animation.js      # AMD module for facilitator podium animation
│       ├── events.js         # PubSub event constants (ANSWER, REVEAL_RANK)
│       ├── facilitator.js    # AMD module for facilitator game control
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
│   │   ├── duplicate_question.php # Question duplication web service (supports optional targetroundid for cross-round duplication)
│   │   └── preview_questions.php # Question preview web service
│   ├── form/                 # Dynamic forms
│   │   ├── join.php          # Join round form (identity mode aware, normalises displayname in get_data)
│   │   └── question.php      # Question add/edit modal form
│   ├── privacy/
│   │   └── provider.php      # Privacy API provider (metadata, export, delete for participants/responses)
│   ├── local/
│   │   ├── entities/         # Domain entity classes
│   │   │   ├── participant.php # Participant entity (avatar, display name, scoring)
│   │   │   ├── rank.php      # Participant ranking entity (score, rank, ties)
│   │   │   ├── round.php     # Round entity with caching for kahoodle/cm/context/guess_context
│   │   │   ├── round_question.php # Round question entity (joins 3 tables)
│   │   │   └── round_stage.php # Round stage entity (current stage in a round)
│   │   ├── game/             # Game mechanics
│   │   │   ├── participants.php # Participant management (join, avatar save, get)
│   │   │   ├── progress.php  # Game progress and stage transitions
│   │   │   └── realtime_channels.php # Realtime channel management
│   │   └── questiontypes/    # Question type implementations
│   │       ├── base.php      # Abstract base class for question types
│   │       └── multichoice.php # Multiple choice question type
│   ├── output/               # Output classes for templates
│   │   ├── facilitator.php   # Facilitator view template data preparation
│   │   ├── landing.php       # Landing page output (stage-based view)
│   │   ├── participant.php   # Participant view template data preparation
│   │   ├── renderer.php      # Plugin renderer
│   │   ├── results.php       # Results page output (all rounds with status)
│   │   └── roundquestion.php # Round question display output
│   └── reportbuilder/local/  # Report builder components
│       ├── entities/
│       │   ├── participant.php # Participant entity for reports
│       │   ├── question.php  # Question entity (kahoodle_questions table)
│       │   ├── question_version.php # Question version entity (kahoodle_question_versions table)
│       │   ├── response.php  # Response entity for participant answers
│       │   ├── round.php     # Round entity for reports (name column/filter)
│       │   └── round_question.php # Round question entity (kahoodle_round_questions table)
│       └── systemreports/
│           ├── all_rounds_participants.php # All rounds participants system report
│           ├── all_rounds_statistics.php # All rounds statistics system report
│           ├── participant_answers.php # Participant answers system report
│           ├── participants.php # Round participants system report
│           ├── questions.php # Questions list system report
│           └── statistics.php # Question statistics system report
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
│   │   ├── behat_mod_kahoodle.php      # Custom behat step definitions
│   │   ├── basic_actions.feature       # CRUD, settings, duplication scenarios
│   │   ├── lobby.feature               # Lobby join form and identity modes
│   │   ├── question_management.feature # Question add/edit/delete/reorder/duplicate
│   │   ├── multiround_results.feature  # Multi-round results reports (participants, statistics, answers, all-rounds, question versioning)
│   │   ├── results_reports.feature     # Single-round results reports
│   │   ├── gameplay_participant.feature # Participant gameplay flow (all stages)
│   │   └── gameplay_facilitator.feature # Facilitator game control (all stages)
│   ├── external/             # Web service tests
│   │   ├── add_questions_test.php
│   │   ├── change_question_sortorder_test.php
│   │   ├── create_instance_test.php
│   │   ├── delete_question_test.php
│   │   └── duplicate_question_test.php
│   ├── generator/            # Test data generators
│   │   ├── behat_mod_kahoodle_generator.php  # Behat generator (questions, participants, responses)
│   │   └── lib.php                           # PHPUnit generator
│   ├── local/
│   │   ├── entities/
│   │   │   ├── rank_test.php             # Rank entity display methods
│   │   │   └── round_test.php            # Round entity tests
│   │   ├── game/
│   │   │   ├── participants_test.php     # Participant management tests
│   │   │   ├── progress_test.php         # Game progress and stage transitions
│   │   │   └── responses_test.php        # Response recording and scoring
│   │   └── questiontypes/
│   │       └── multichoice_test.php      # Multichoice question type tests
│   ├── output/
│   │   ├── facilitator_test.php          # Facilitator output class tests
│   │   ├── landing_test.php              # Landing page output tests
│   │   ├── participant_test.php          # Participant output class tests
│   │   ├── results_test.php              # Results page output tests
│   │   └── roundquestion_test.php        # Round question output tests
│   ├── privacy/
│   │   └── provider_test.php # Privacy provider tests (metadata, contexts, export, delete)
│   ├── reportbuilder/local/systemreports/  # Report builder system report tests
│   │   ├── helper.php                    # Shared test helper trait
│   │   ├── all_rounds_participants_test.php
│   │   ├── all_rounds_statistics_test.php
│   │   ├── participant_answers_test.php
│   │   ├── participants_test.php
│   │   ├── questions_test.php
│   │   └── statistics_test.php
│   ├── api_test.php          # PHPUnit tests for api.php
│   ├── backup_restore_test.php # Backup/restore tests (with/without userdata, files, mixed)
│   ├── constants_test.php    # Tests that field list constants match DB schema
│   ├── events_test.php       # Event triggering tests
│   ├── lib_test.php          # lib.php callback tests
│   └── questions_test.php    # PHPUnit tests for questions API
├── index.php                 # List all instances in a course
├── lib.php                   # Core module functions (includes inplace_editable callback)
├── mod_form.php              # Activity settings form
├── questions.php             # Question management page (accepts id or roundid)
├── results.php               # Results page showing all rounds
├── version.php               # Plugin version information
└── view.php                  # Main view page (landing page with actions, newround action supports returnto parameter)
```

## Core Functionality

### Constants

All plugin constants are defined in `classes/constants.php`:

- **Default Values**: Activity defaults (lobby duration, question timing, points)
- **Question Format**: `QUESTIONFORMAT_PLAIN` (0) for plain text with image, `QUESTIONFORMAT_RICHTEXT` (1) for rich text editor
- **Text Limits**: `QUESTIONTEXT_MAXLENGTH` (300 characters for plain text mode)
- **Round Stages**: `STAGE_PREPARATION`, `STAGE_LOBBY`, `STAGE_QUESTION_PREVIEW`, `STAGE_QUESTION`, `STAGE_QUESTION_RESULTS`, `STAGE_LEADERS`, `STAGE_REVISION`, `STAGE_ARCHIVED`
- **Identity Mode**: `IDENTITYMODE_REALNAME` (0) real name, `IDENTITYMODE_OPTIONAL` (1) optional alias, `IDENTITYMODE_ALIAS` (2) required alias, `IDENTITYMODE_ANONYMOUS` (3) fully anonymous
- **File Areas**: `FILEAREA_QUESTION_IMAGE` for question images, `FILEAREA_AVATAR` for participant avatars
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
   - Works in any round (not just fully editable ones)
   - Validates changes against existing responses via question type's `validate_edit_changes()`

6. **`delete_question(round_question $roundquestion): void`**
   - Removes question from fully editable round
   - Preserves version if used in started rounds
   - Deletes version and question if not used elsewhere
   - Throws exception if round is not fully editable

7. **`duplicate_question(round_question $roundquestion, ?round $targetround = null): round_question`**
   - Creates a copy of the question (new question + version + files)
   - When duplicating within the same round, places the duplicate right after the original in sort order
   - When duplicating to a different round (`$targetround`), appends at the end
   - Only works if the target round is fully editable
   - Returns the newly created round_question entity

8. **`question_has_responses(int $questionid): bool`**
   - Checks if any version of a question has responses from participants
   - Used to determine whether edit restrictions apply

#### Versioning Logic

- **Fully Editable Round**: Questions in preparation-stage rounds (not yet started) can be freely edited, added, deleted, reordered, and duplicated
- **Started Rounds**: Once a round starts, questions can still be edited (content changes) but cannot be added, deleted, or reordered. Questions can be duplicated into a different fully editable round (cross-round duplication).
- **Edit Restrictions**: When a question has existing responses, the question type may restrict certain changes (e.g., multichoice prevents changing the number of options or the correct answer position)
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
- `is_fully_editable(): bool` - Check if round is in preparation stage (can add/delete/reorder/duplicate questions)
- `get_kahoodle(): stdClass` - Get cached kahoodle activity record
- `get_cm(): stdClass` - Get cached course module record
- `get_context(): context_module` - Get context module instance
- `guess_context(): context_module` - Get context from `$PAGE->context` if available (avoids DB query), falls back to `get_context()`
- `get_name_inplace_editable(): inplace_editable` - Get inplace editable control for round name
- `update_name(string $name): inplace_editable` - Update round name and return inplace editable
- `duplicate(): self` - Create a new round by duplicating question configuration from this round
- `get_all_participants(): array` - Get all participants in the round (cached)
- `get_participants_count(): int` - Get count of participants (uses cache or COUNT query)
- `get_rankings(): array` - Get rankings for all participants (keyed by participant ID)
- `update_final_ranks(): void` - Update finalrank and totalscore for all participants (called on revision stage)

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

#### participant Entity (`participant.php`)

Represents a participant in a round with display name, avatar, and scoring data.

**Factory Methods:**
- `from_partial_record(stdClass $record, ?round $round = null): self` - Create from a partial database record (used by reportbuilder callbacks)

**Methods:**
- `get_id(): int` - Get participant ID
- `get_display_name(): string` - Get display name (falls back to user fullname)
- `get_avatar_url(): moodle_url` - Get stored avatar URL (pluginfile URL) or default user picture
- `get_profile_picture_url(int $size = 35): moodle_url` - Get Moodle profile picture URL (not stored avatar)
- `get_final_rank(): ?int` - Get final rank (null if round not finished)

#### rank Entity (`rank.php`)

Represents a participant's score and rank in a round, including tie information.

**Properties:**
- `participant`: The participant entity
- `score`: Total score after this question
- `minrank`: Minimum rank (best possible in case of ties)
- `maxrank`: Maximum rank (worst possible in case of ties)
- `tiewith`: Array of participants tied with this participant
- `prevscore`: Score of the participant with the previous rank
- `withprevscore`: Array of participants with the previous score
- `prevquestionrank`: Rank after the previous question (for showing rank movement)

**Factory Methods:**
- `create_empty(participant $participant): rank` - Create empty rank for no data

**Methods:**
- `get_data_for_revision(): array` - Get template data for revision screen (rank image, header, status)
- `get_data_for_question_results(): array` - Get template data for question results
- `get_rank_movement_status(): int` - Get rank movement (positive=down, negative=up, 0=no change)
- `get_rank_as_range(): string` - Get rank as "4" or "2-5" for ties

#### round_stage Entity (`round_stage.php`)

Represents the current stage in a round. A stage can be a non-question stage (lobby, leaders, revision) or a question stage (preview, question, results) associated with a specific round_question.

**Factory Methods:**
- `create_from_round_question(round_question $roundquestion, string $stagename): self` - Create for a question stage

**Methods:**
- `get_round(): round` - Get the parent round
- `get_stage_name(): string` - Get the stage constant (STAGE_LOBBY, STAGE_QUESTION, etc.)
- `get_round_question(): ?round_question` - Get associated round question (null for non-question stages)
- `get_duration(): int` - Get duration in seconds
- `get_question_number(): int` - Get question number (1-based), 0 for non-question stages
- `is_question_stage(): bool` - Check if this is a question-related stage
- `matches(string $stagename, int $questionnumber): bool` - Check if stage matches given parameters

### Output Classes

The plugin uses output classes in `classes/output/` for template data preparation. These classes implement `\renderable` and `\templatable` interfaces.

#### facilitator Output Class (`output/facilitator.php`)

Prepares template data for the facilitator view managing a kahoodle round. Used when rendering the facilitator interface during gameplay.

**Constructor:**
- `__construct(round $round)` - Takes the round entity

**Key Methods:**
- `export_for_template(\renderer_base $output): array` - Main export method
- `get_template(): ?string` - Returns template name for current stage
- `get_duration(): int` - Returns auto-advance duration for current stage
- `get_common_data(): array` - Returns data common to all stages
- `get_lobby_data(): array` - Returns lobby-specific template data
- `get_question_data(): array` - Returns question stage template data
- `get_leaderboard_data(): array` - Returns leaderboard template data (leaders/revision)

**Returned Data Structure:**
```php
[
    'stage' => 'lobby|questionpreview|question|questionresults|leaders|revision|archived',
    'currentquestion' => 0,        // Question number (0 for non-question stages)
    'totalquestions' => 5,         // Total questions in round
    'template' => 'mod_kahoodle/facilitator/lobby',  // Template to render
    'duration' => 60,              // Auto-advance duration in seconds
    'templatedata' => [            // Data passed to the template
        'quiztitle' => '...',
        'sortorder' => 1,
        'totalquestions' => 5,
        'cancontrol' => true,
        'isedit' => false,
        'backgroundurl' => '...',
        // ... stage-specific data
    ],
]
```

#### participant Output Class (`output/participant.php`)

Prepares template data for the participant view playing a kahoodle round. Used when rendering the participant interface during gameplay.

**Constructor:**
- `__construct(participant_entity $participant)` - Takes the participant entity

**Key Methods:**
- `export_for_template(\renderer_base $output): array` - Main export method
- `get_template(): string` - Returns template name for current stage
- `get_common_data(): array` - Returns data common to all stages
- `get_lobby_data(): array` - Returns lobby-specific template data
- `get_question_data(): array` - Returns question stage template data (preview, question, results, leaders)
- `get_revision_data(): array` - Returns revision stage template data

**Returned Data Structure:**
```php
[
    'stagesignature' => 'lobby',   // Unique stage identifier
    'currentquestion' => 1,
    'totalquestions' => 5,
    'duration' => 0,               // No auto-advance for participants
    'template' => 'mod_kahoodle/participant/lobby',
    'templatedata' => [
        'quiztitle' => '...',
        'avatarurl' => '...',
        'displayname' => '...',
        'totalscore' => 1500,
        // ... stage-specific data
    ],
]
```

#### roundquestion Output Class (`output/roundquestion.php`)

Prepares template data for displaying a single question. Used by both facilitator and participant output classes during question stages.

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
- `validate_edit_changes(round_question $roundquestion, \stdClass $newdata): array` - Validate proposed edit changes against existing responses. Returns error messages if changes are not allowed. Default implementation allows all changes; override in subclasses to add restrictions.

#### Multichoice Type (`multichoice.php`)

Multiple choice questions with 2-8 answer options.

**questionconfig Format:**
- One option per line
- Prefix correct answer with asterisk (`*`)
- Example: `"Option A\n*Option B\nOption C"` (Option B is correct)

**Edit Restrictions (when responses exist):**
- Cannot change the number of answer options
- Cannot change the position of the correct answer

**Validation:**
- Requires 2-8 options
- Exactly one option must be marked as correct

### Report Builder Components

The plugin uses Moodle Report Builder for displaying tabular data with filtering, sorting, and export capabilities.

#### Entities (`reportbuilder/local/entities/`)

The question-related entities follow a consistent join direction: **kahoodle → question → question_version → round_question**. Each entity provides a join method and assumes the previous entities in the chain are already joined.

**question Entity (`question.php`)**
Provides columns and filters for question base data (kahoodle_questions table).

**Join Method:** `get_questions_join()` - joins kahoodle_questions to kahoodle (assumes kahoodle is already the main table or joined)

**Columns:**
- `questiontype`: Type of question (multichoice, etc.)

**Filters:** `questiontype`

**question_version Entity (`question_version.php`)**
Provides columns and filters for question version data (kahoodle_question_versions table).

**Join Method:** `get_question_versions_join()` - joins kahoodle_question_versions to kahoodle_questions (assumes questions is already joined)

**Columns:**
- `questiontext`: Question text content
- `questionimages`: Question images (for plain text format)
- `version`: Question version number

**Filters:** `questiontext`

**round_question Entity (`round_question.php`)**
Provides columns for round-specific question settings (kahoodle_round_questions table).

**Join Method:** `get_round_questions_join()` - joins kahoodle_round_questions to kahoodle_question_versions (assumes question_versions is already joined)

**Columns:**
- `sortorder`: Question order in round
- `timing`: Preview/question/results durations (shows defaults in normal, overrides in bold)
- `score`: Min-max points range (shows defaults in normal, overrides in bold)

**Note:** Statistics columns (totalparticipants, correctresponses, averagescore) are defined directly in the statistics reports using LEFT JOINs and aggregation, not in the entity.

**participant Entity (`participant.php`)**
Provides columns and filters for displaying round participants. Declares `kahoodle`, `kahoodle_participants`, and `user` tables.

**Join Method:** `get_user_join()` - LEFT JOIN to user table (allows showing participants even if user is deleted)

**Columns:**
- `participant`: Stored avatar + display name (uses `from_partial_record()` with `get_avatar_url()` and `get_display_name()`)
- `rank`: Final rank in round
- `score`: Total score
- `correctanswers`: Count of correct answers (subquery)
- `questionsanswered`: Count of questions answered (subquery)

**Filters:** `displayname`, `rank`, `score`

**Note:** The `participant` column requires the `kahoodle` table to be available (either as main table or via joins). System reports must provide the join chain from kahoodle to kahoodle_rounds to kahoodle_participants. The user entity is added separately by system reports for user-specific columns/filters.

**response Entity (`response.php`)**
Provides columns and filters for displaying participant response data.
Assumes kahoodle_round_questions, kahoodle_question_versions, and kahoodle_questions tables are already joined.
Question-related columns should come from the question/question_version entities.

**Columns:**
- `correct`: Answer correctness (Yes/No/No answer) displayed as colored badges
- `score`: Points earned for this question
- `responsetime`: Time taken to answer (in seconds)
- `response`: Formatted response text (uses question type's `format_response()` method)

**Filters:** `correct` (select with Yes/No/No answer), `score`

**round Entity (`round.php`)**
Provides columns and filters for displaying round information.

**Columns:**
- `name`: Round name (plain text, falls back to "Round N" if empty)
- `namelinked`: Round name with link to participants view

**Filters:** `name`

#### System Reports (`reportbuilder/local/systemreports/`)

**questions Report (`questions.php`)**
- Lists questions for a round on the question management page
- Columns: sortorder (with drag handle in fully editable rounds), questiontype, questionimages, questiontext, timing, score
- Actions: preview (always), edit (always), duplicate (fully editable rounds, or non-editable rounds when an editable round exists — adds `data-targetroundid` attribute for cross-round duplication), delete (fully editable rounds only)
- Caches `get_editable_round_id()` result on the report instance to avoid repeated DB queries per row
- Used in: `questions.php`

**participants Report (`participants.php`)**
- Lists participants for a completed round
- Main table: `kahoodle`, with joins to rounds then participants
- Columns: participant:participant, user:fullnamewithpicturelink, rank, score, correctanswers, questionsanswered
- In anonymous mode: user entity, user column, and user filter are excluded
- Actions: View answers (links to participant details)
- Sorted by score (descending)
- Downloadable
- Used in: `results.php?view=participants&roundid=X`

**participant_answers Report (`participant_answers.php`)**
- Shows all answers for a specific participant
- Uses both question entity (for question columns) and response entity (for response columns)
- Columns: question:sortorder, question:questiontype, question:questionimages, question:questiontext, response:response, response:correct, response:score, response:responsetime
- Filters: question:questiontext, response:correct, response:score
- Shows all questions (even unanswered ones via LEFT JOIN to responses)
- Sorted by question order (ascending)
- Downloadable
- Used in: `results.php?participantid=X&view=details`
- Header shows: back link to participants, participant avatar/name, user picture/name, total score

**statistics Report (`statistics.php`)**
- Shows question statistics for a completed round
- Uses LEFT JOIN through participants to responses (ensures non-responders are counted with 0 points)
- Columns: sortorder, questiontype, questionimages, questiontext, correctresponses, averagescore
- Total participants count is shown as a subheader above the report (not in table)
- Statistics columns use aggregation (SUM for correct, AVG for score) instead of subqueries
- Downloadable
- Used in: `results.php?view=statistics&roundid=X`

**all_rounds_participants Report (`all_rounds_participants.php`)**
- Shows participants from all completed rounds (revision or archived) for a kahoodle activity
- Main table: `kahoodle`, with joins to rounds then participants
- Uses round entity for round name column and filter
- Columns: round:namelinked, participant:participant, user:fullnamewithpicturelink, rank, score, correctanswers, questionsanswered
- In anonymous mode: user entity, user column, and user filter are excluded
- Filters: round:name, displayname, user:userselect, rank, score
- Actions: View answers (links to participant details)
- Downloadable
- Used in: `results.php?id=X&view=allparticipants`
- Only visible when there are 2+ completed rounds

**all_rounds_statistics Report (`all_rounds_statistics.php`)**
- Shows question statistics aggregated across all rounds for each question in a kahoodle activity
- Uses LEFT JOINs: question → versions (islast=1) → round_questions → participants → responses
- Columns: sortorder (from last round), questiontype, questionimages, questiontext, totalparticipants, correctresponses, averagescore
- Filters: questiontype, questiontext
- Statistics columns use aggregation (SUM for participants/correct, AVG for score) instead of subqueries
- Non-responders are included in averages with 0 points (via LEFT JOIN through participants)
- Downloadable
- Used in: `results.php?id=X&view=allstatistics`

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
- `identitymode`: Identity mode (0=real name, 1=optional alias, 2=required alias, 3=fully anonymous)
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
- `islast`: 1 if this is the latest version of the question, 0 otherwise (maintained by questions API)
- `timecreated`, `timemodified`: Timestamps

**Unique Index:** `questionid, version` - ensures one version record per version number

**Usage:**
- When question is created: Insert version 1 with `islast=1`
- When question is edited: Set `islast=0` for all existing versions, insert new version with `islast=1`
- When latest version is deleted: Update the previous version to `islast=1`
- Rounds reference specific version IDs for historical accuracy
- The `islast` field enables efficient queries to find the current version of each question

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
- `userid`: FK to core user table (nullable — NULL in anonymous mode)
- `participantcode`: MD5 hash for anonymous participant identification within a session (nullable — NULL in non-anonymous modes)
- `displayname`: Custom display name chosen by participant
- `avatar`: Filename of stored avatar in `mod_kahoodle/avatar/{participantid}` file area (copied from user's profile picture on join, null if no picture)
- `totalscore`: Total points earned in round
- `finalrank`: Final leaderboard position

**Unique Indexes:**
- `roundid, userid` - One participation per user per round in non-anonymous modes
- `roundid, participantcode` - One participation per code per round in anonymous mode

**Anonymous Mode:** In fully anonymous mode (`identitymode=3`), `userid` is NULL and `participantcode` is set to `md5($USER->id . sesskey() . $roundid)`. This is deterministic within a session (stable across page refreshes) but changes on re-login. The participant is looked up by `participantcode` instead of `userid`.

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

#### Backup/Restore Tests (`tests/backup_restore_test.php`)
- Tests backup/restore with user data (all rounds, participants, responses preserved)
- Tests backup/restore without user data (only last round and its questions)
- Tests backup with user data, restore without (all rounds but no participants)
- Tests question image files are backed up and restored
- Tests participant avatar files are backed up and restored
- Tests single round backup without user data

#### Constants Tests (`tests/constants_test.php`)
- Verifies `FIELDS_QUESTION_VERSION` matches `kahoodle_question_versions` table columns
- Verifies `FIELDS_ROUND_QUESTION` matches `kahoodle_round_questions` table columns

#### Privacy Provider Tests (`tests/privacy/provider_test.php`)
- Tests metadata declaration (tables and subsystems)
- Tests context discovery for users with participation data
- Tests user enumeration within contexts
- Tests user data export (participations with responses)
- Tests deletion for all users in context, single user, and multiple users
- Tests multiple kahoodle instances return separate contexts

#### Web Service Tests (`tests/external/`)
- `add_questions_test.php`: Tests for batch question creation (single/multiple, permissions, error handling, mixed success)
- `delete_question_test.php`: Tests for question deletion (basic, permissions, sortorder fix)
- `duplicate_question_test.php`: Tests for question duplication (same-round, cross-round with targetroundid, permissions)
- `change_question_sortorder_test.php`: Tests for question reordering
- `create_instance_test.php`: Tests for activity instance creation

#### Game Logic Tests (`tests/local/game/`)
- `progress_test.php`: Game progress and stage transition tests
- `responses_test.php`: Response recording, scoring, and points calculation
- `participants_test.php`: Participant management (join, avatar, get)

#### Entity Tests (`tests/local/entities/`)
- `rank_test.php`: Rank entity display methods (revision data, question results, rank suffix)
- `round_test.php`: Round entity tests

#### Question Type Tests (`tests/local/questiontypes/`)
- `multichoice_test.php`: Multichoice sanitization, validation, format_response, export_template_data

#### Output Class Tests (`tests/output/`)
- `facilitator_test.php`: Facilitator view template data for all stages
- `participant_test.php`: Participant view template data for all stages
- `landing_test.php`: Landing page output (preparation, in-progress, archived, join form)
- `results_test.php`: Results page output (round ordering, status display)
- `roundquestion_test.php`: Round question display output

#### Report Builder Tests (`tests/reportbuilder/local/systemreports/`)
- One test file per system report, each with the matching namespace
- Shared helper trait (`helper.php`) provides `create_dataset()`, `create_second_round()`, and `setup_page()`
- `questions_test.php`: Questions list report
- `participants_test.php`: Round participants report (+ anonymous mode)
- `participant_answers_test.php`: Participant answers report
- `statistics_test.php`: Question statistics report
- `all_rounds_participants_test.php`: All rounds participants report
- `all_rounds_statistics_test.php`: All rounds statistics report

#### Other Tests
- `events_test.php`: Event triggering tests for all 10 event classes
- `lib_test.php`: lib.php callback tests (supports, add/update/delete instance, pluginfile, inplace_editable)

### Test Data Generator

The `mod_kahoodle_generator` (in `tests/generator/lib.php`) provides:
- `create_instance($record)`: Create kahoodle activity instances
- `create_round($record)`: Create rounds directly (requires `kahoodleid`, optional `currentstage`, `currentquestion`, etc.)
- `create_question($record)`: Create questions with all parameters (auto-creates round if needed)
- `create_participant($record)`: Create participants for rounds (requires `roundid` and `userid`)
- `create_response($record)`: Create participant responses (requires `participantid` and `roundquestionid`)

### Behat Acceptance Tests

The plugin includes Behat acceptance tests for participant and facilitator workflows.

#### Round Stage Signatures

Stages are identified by signature strings used in the behat step and in `data-stage` attributes:

| Stage | Signature | Description |
|---|---|---|
| Preparation | `preparation` | Questions being set up, game not started |
| Lobby | `lobby` | Waiting for participants to join |
| Question Preview | `preview-N` | Showing question N text before answering |
| Question | `question-N` | Participants answering question N |
| Question Results | `results-N` | Showing results for question N |
| Leaders | `leaders-N` | Leaderboard after question N |
| Revision | `revision` | Final leaderboard with podium |
| Archived | `archived` | Game finished |

**Important:** The stage signature format is `preview-N`, `question-N`, `results-N`, `leaders-N` (NOT `questionpreview-N`, `questionresults-N`). The `N` is 1-based question number.

#### Custom Behat Steps (`tests/behat/behat_mod_kahoodle.php`)

Three custom steps are available:

1. **Advance round to a specific stage:**
   ```gherkin
   Given the kahoodle "Test Kahoodle" round stage is "lobby"
   ```
   Starts from preparation and advances through each stage until target is reached. Can be called multiple times — if the round is already past preparation, it continues from the current stage.

2. **Reveal participant ranks during revision:**
   ```gherkin
   When the kahoodle "Test Kahoodle" rank "rank3" is revealed
   ```
   Calls `realtime_channels::notify_participants_rank_revealed()`. Valid values: `rank1`, `rank2`, `rank3`, `all`.

3. **Join a user as participant:**
   ```gherkin
   When "student1" joins the kahoodle "Test Kahoodle"
   ```
   Calls `participants::join_round()` as the specified user. Triggers the realtime notification so the facilitator overlay updates. Uses real name identity mode by default.

#### Behat Data Generator (`tests/generator/behat_mod_kahoodle_generator.php`)

Three entities can be created in Behat scenarios:

```gherkin
# Questions (creates round automatically if needed)
And the following "mod_kahoodle > questions" exist:
  | kahoodle      | questiontext | questionconfig      |
  | Test Kahoodle | Question 1   | Option A\n*Option B |

# Participants (requires round to exist - advance stage first)
And the following "mod_kahoodle > participants" exist:
  | kahoodle      | user     | displayname |
  | Test Kahoodle | student1 | Sam         |

# Responses (requires participant to exist)
And the following "mod_kahoodle > responses" exist:
  | kahoodle      | user     | questiontext | response | iscorrect | points |
  | Test Kahoodle | student1 | Question 1   | 2        | 1         | 900    |
```

**Key notes:**
- `kahoodle` field uses the activity **name** (not idnumber)
- `questionconfig` uses literal `\n` for newlines, `*` prefix for correct answer
- Participants/responses resolve the round via `questions::get_last_round()`
- Create the round first (e.g., via `the kahoodle round stage is "lobby"`) before creating participants
- Response `questiontext` must match an existing question exactly

#### Facilitator Overlay Behavior

The facilitator overlay (`facilitator.js`) is important to understand for writing tests:

- **Auto-opens on page load:** When a teacher visits the activity page and a game is in progress (any stage except preparation/archived), the overlay opens automatically. No need to click "Resume facilitating".
- **Wait for overlay:** Always use `And I wait until ".mod_kahoodle-overlay" "css_element" exists` after navigating to the page.
- **Next button:** `[data-action='next']` advances to the next stage via AJAX/realtime. The overlay content updates dynamically without page reload.
- **Close button:** `[data-action='close']` closes the overlay, returning to the landing page. Only visible when NOT in revision stage.
- **Finish activity button:** In revision stage, the next button is replaced by "Finish activity" (`[data-action='next']` with different text).
- **Pause/Resume:** `[data-action='pause']` and `[data-action='resume']` toggle autoplay.
- **Landing page buttons:** "End round for everyone" (`[data-action='finish-game']`) archives the game. "Resume facilitating" (`[data-action='resume-game']`) opens the overlay. "Allow participants to join" is a regular link that starts the game.

#### Participant Overlay Behavior

The participant overlay (`participant.js`) updates via realtime:

- **Stage content elements:** Each stage renders a specific content div:
  - Lobby: `.mod_kahoodle-participant-container`
  - Preview: `.mod_kahoodle-participant-preview-content`
  - Question: `.mod_kahoodle-participant-question-content`
  - Results: `.mod_kahoodle-participant-result-content`
  - Leaders: `.mod_kahoodle-participant-result-content` + `.mod_kahoodle-participant-rank`
  - Revision: `.mod_kahoodle-participant-revision-content`
- **Wait pattern:** After advancing stage via behat step, always wait for the content element:
  ```gherkin
  When the kahoodle "Test Kahoodle" round stage is "question-1"
  And I wait until ".mod_kahoodle-participant-question-content" "css_element" exists
  ```
- **Answer options:** Multichoice options use `.mod_kahoodle-option1`, `.mod_kahoodle-option2`, etc.
- **Visibility checks:** Use `I should see` / `I should not see` to check text visibility. Do NOT use `should exist` / `should not exist` for elements that are hidden via CSS `display: none` — they exist in the DOM but are not visible.

#### Key Template CSS Selectors for Assertions

**Facilitator templates:**
- `.mod_kahoodle-overlay` — main overlay container
- `.mod_kahoodle-game-title` — quiz title in lobby header
- `.mod_kahoodle-participants-list` — participant list in lobby
- `.mod_kahoodle-participant-name` — individual participant name
- `.mod_kahoodle-qr-code` — QR code element in lobby
- `.mod_kahoodle-question-counter` — "X of Y" counter
- `.mod_kahoodle-question-text` — question text
- `.mod_kahoodle-option-text` — answer option text
- `.mod_kahoodle-option-correct` — correct answer in results
- `.mod_kahoodle-correct-badge` — checkmark on correct answer
- `.mod_kahoodle-leaderboard-title` — "Leaderboard" heading
- `.mod_kahoodle-leaderboard-name` — participant name in leaderboard
- `[data-stage='lobby']`, `[data-stage='preview']`, etc. — stage identifier

**Participant templates:**
- `.mod_kahoodle-participant-result-total-value` — total score display
- `.mod_kahoodle-participant-result-score` — points earned for a question
- `.mod_kahoodle-participant-rank` — rank message (hidden by default, shown in leaders/revision)

#### Running Behat Tests

See `CLAUDE.local.md` for environment-specific Docker commands. Feature files use the `@mod_kahoodle` tag.

### Running PHPUnit Tests

```bash
# Run all kahoodle tests (from Moodle root directory)
vendor/bin/phpunit --testsuite mod_kahoodle_testsuite

# Run specific test file
vendor/bin/phpunit mod/kahoodle/tests/questions_test.php

# Run with filter
vendor/bin/phpunit --filter questions_test
```

**Note:** If you see "Moodle PHPUnit environment was initialised for different version", run `php admin/tool/phpunit/cli/init.php` to reinitialize.

## Current Status

**Implemented:**
- Database schema with versioning system
- Question format modes (plain text with image OR rich text editor)
- Question management API with smart versioning
- Question edit validation: warns when responses exist, question types can restrict changes (multichoice prevents changing option count or correct answer position)
- Question duplication (same-round and cross-round to fully editable rounds)
- Batch question creation web service
- Question deletion web service
- Question duplication web service
- Entity classes for `round` and `round_question` with caching
- Questions management page with Report Builder system report
- Question add/edit modal form (dynamic form with conditional fields based on format)
- AMD module for question UI interactions
- Constants for defaults, types, stages, file areas, and field lists
- Comprehensive test coverage (179 PHPUnit tests)
- Test data generators
- Backup/restore with full support for questions, rounds, participants, responses, and files
  - Without user data: backs up only the last round and its questions (latest versions)
  - With user data: backs up all rounds, participants, responses, question images, and avatar files
  - Backed up with user data but restored without: restores all rounds/questions but no participants
- Privacy API provider (metadata, export, delete for participants and responses)
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
  - Participants view: system report showing all participants with rank, score, and answer counts
  - Statistics view: system report showing question statistics with response counts and average scores
  - All rounds participants/statistics buttons (shown when 2+ completed rounds)
- Report Builder integration
  - Question entities split into three: question (type), question_version (text, images), round_question (sortorder, timing, score)
  - Consistent join direction: kahoodle → question → question_version → round_question
  - Participant entity with columns for participant data and answer statistics
  - Response entity for participant answers (assumes question tables are joined)
  - Round entity with columns for round name (used in all-rounds reports)
  - Six system reports: questions (management), participants (results), statistics (results), all_rounds_participants, all_rounds_statistics, participant_answers
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
- Identity mode setting (realname, optional alias, required alias, fully anonymous)
- Fully anonymous mode: userid is NULL in participants table, participantcode used for session-scoped identification, user columns/filters hidden in reports, events suppressed
- Participant avatar storage (profile picture saved on join for consistent display in reports)

**In Progress:**
- Round gameplay mechanics
- Participant response submission and scoring

**To Do:**
- Additional question types
- Viewing the answer distribution chart in the results
- Not covered with automated tests:
    * Managing and displaying questions in rich text format
    * Fully anonymous mode workflow, reports and events
    * When testing web services - validate that the output passes validation with the execute_returns() function
    * add question web service - errors adding questions, WS returning warnings
    * Tests for the question duplication web service;
    * duplicating question with files
    * changing avatars (behat)
    * rank::get_rank_message in different situations
    * previewing questions when editing (behat)
    * facilitator joining as participant (behat)
    * editing questions with responses (behat)
    * adding and editing questions with images (behat)
