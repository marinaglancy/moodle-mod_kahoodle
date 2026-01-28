# Kahoodle - Real-time Quiz Activity for Moodle

Kahoodle is a Moodle activity module that enables real-time, interactive quiz sessions where all participants take a quiz simultaneously. Designed for engaging, game-like quiz experiences similar to popular classroom quiz platforms.

## Features

- **Real-time gameplay** - All participants answer questions simultaneously
- **Live leaderboard** - Points based on correctness and response speed
- **Multiple rounds** - Run multiple quiz sessions with the same questions
- **Question versioning** - Edit questions while preserving historical round data
- **Mobile-friendly** - Responsive design for participant devices

## Screenshots

<!-- Screenshot: Facilitator view showing lobby with participants -->
![Facilitator Lobby](docs/screenshots/facilitator-lobby.png)

<!-- Screenshot: Participant view answering a question -->
![Participant Question](docs/screenshots/participant-question.png)

## Requirements

- Moodle 4.5 or higher
- [tool_realtime](https://github.com/marinaglancy/moodle-tool_realtime) plugin for real-time communication using PHP polling or websockets.

## Usage

1. Add a Kahoodle activity to your course
2. Create questions in the Questions tab
3. Start a round and share your screen with participants (the lobby displays a QR code with the activity link)
5. Participants join with a display name and avatar
4. Questions advance automatically, or you can pause and advance manually

## Installation

1. Download the plugin
2. Extract to your Moodle installation:
   - Moodle 4.5 and 5.0: `mod/kahoodle/`
   - Moodle 5.1 and above: `public/mod/kahoodle/`
3. Visit Site administration > Notifications to complete installation

## Additional Features

- **AI-powered content generation**: The plugin provides web services to create instances and add questions. Use with [Moodle MCP](https://lmscloud.io/products/moodle-mcp/) to quickly generate content using your preferred AI agent.
- **WebSocket hosting**: An additional plugin for tool_realtime is available to connect to a WebSocket solution that can be hosted on LMSCloud or self-hosted.
- **Quick access**: An additional plugin allows participants to join Kahoodle activities without needing a permanent account on the Moodle site.

Contact [LMSCloud](https://lmscloud.io) for more details.

## Acknowledgements

This plugin was originally developed during DevCamp at MoodleMoot DACH 2025 by: Marina Glancy, Jan Britz, Immanuel Pasanec, Vasco Grossmann, Lars Dreier, Kathleen Aermes, and Monika Weber.

The code from the DevCamp can be found in the [poc branch](https://github.com/marinaglancy/moodle-mod_kahoodle/tree/poc).

The current version was created with assistance of [Claude Code](https://claude.ai/claude-code) and [Lovable](https://lovable.dev).

## License

GNU GPL v3 or later - https://www.gnu.org/copyleft/gpl.html
