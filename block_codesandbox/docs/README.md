# CodeSandbox Block

> **⚠️ Disclaimer:** Proyecto experimental. Úsalo bajo tu propio riesgo.

**Real-time execution monitoring sidebar for Code Sandbox activities**

## Overview

The CodeSandbox Block is a companion plugin for the `mod_codesandbox` activity module. It provides students with a persistent sidebar that displays:

- 📸 **Last Run Snapshot**: Preview of the most recent code execution with status and runtime
- 📊 **Execution History**: Timeline of recent runs with success/failure indicators  
- 📝 **Scratchpad**: Auto-saving notepad tied to each activity instance

This block enhances the learning experience by giving students continuous visibility into their coding progress without leaving the activity page.

## Features

### 1. Last Run Snapshot
Displays a compact preview of the most recently executed code:
- Code preview (first 10 lines)
- Execution status (success/error)
- Runtime in milliseconds
- Timestamp of execution

### 2. Execution History
Shows the last 5 code runs with:
- Success/failure status icons
- Execution timestamps
- Runtime metrics
- Color-coded backgrounds for quick visual scanning

### 3. Persistent Scratchpad
A note-taking area that:
- Auto-saves as you type (debounced)
- Stores notes per activity instance (`cmid`)
- Persists across sessions
- Shows real-time save status

## How It Works

### Integration with mod_codesandbox

The block connects to the Code Sandbox activity through a seamless workflow:

```
┌─────────────────┐
│ Student writes  │
│ code in editor  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Clicks "Run"    │
│ button          │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│ mod_codesandbox/run.php │
│ - Creates attempt       │
│ - Sends to RabbitMQ     │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│ Worker executes code    │
│ in Docker container     │
└────────┬────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ mod_codesandbox/callback.php │
│ - Updates attempt record     │
│ - Updates block tables ✨    │
│   • block_codesandbox_snap   │
│   • block_codesandbox_hist   │
└──────────┬───────────────────┘
         │
         ▼
┌─────────────────────┐
│ Page auto-refreshes │
│ Block shows results │
└─────────────────────┘
```

### Database Tables

The block creates three tables:

#### `block_codesandbox_notes`
Stores scratchpad notes per user per activity.

| Field         | Type    | Description                    |
|---------------|---------|--------------------------------|
| id            | int     | Primary key                    |
| userid        | int     | User ID                        |
| cmid          | int     | Course module ID               |
| note_text     | text    | Content of the scratchpad      |
| timemodified  | int     | Last modification timestamp    |

**Unique index**: `(userid, cmid)`

#### `block_codesandbox_snap`
Stores the most recent execution snapshot per user per activity.

| Field         | Type    | Description                    |
|---------------|---------|--------------------------------|
| id            | int     | Primary key                    |
| userid        | int     | User ID                        |
| cmid          | int     | Course module ID               |
| code_preview  | text    | First lines of executed code   |
| status        | char(20)| Execution status (success/error)|
| runtime_ms    | int     | Execution time in milliseconds |
| timestamp     | int     | Execution timestamp            |

**Unique index**: `(userid, cmid)`

#### `block_codesandbox_hist`
Stores execution history (last 5 runs per user per activity).

| Field         | Type    | Description                    |
|---------------|---------|--------------------------------|
| id            | int     | Primary key                    |
| userid        | int     | User ID                        |
| cmid          | int     | Course module ID               |
| status        | char(20)| Execution status               |
| timestamp     | int     | Execution timestamp            |
| runtime_ms    | int     | Runtime in milliseconds        |

**Index**: `(userid, cmid, timestamp)`

## Installation

1. **Copy files** to `blocks/codesandbox/`

2. **Install dependencies** (already included in mod_codesandbox):
   - Requires `mod_codesandbox` to be installed
   - RabbitMQ with php-amqplib

3. **Run Moodle upgrade**:
   ```bash
   php admin/cli/upgrade.php
   ```

4. **Add block to course/activity**:
   - Navigate to a Code Sandbox activity
   - Turn editing on
   - Add the "CodeSandbox Monitor" block
   - The block will automatically detect the activity context

## Requirements

- **Moodle**: 4.1 or higher
- **mod_codesandbox**: v1.0-alpha or higher (dependency)
- **PHP**: 7.4+
- **RabbitMQ**: Configured and running (via mod_codesandbox)

## Context Detection

The block intelligently detects its context:

- ✅ **In Code Sandbox activity**: Shows full interface with execution data
- ℹ️ **Outside activity**: Displays a help message
- 🔒 **Data isolation**: Each activity instance has separate data (by `cmid`)

## Configuration

No additional configuration needed. The block automatically:
- Detects the current activity context
- Loads data specific to the logged-in user
- Connects to the same RabbitMQ instance as mod_codesandbox

## Technical Details

### Auto-save Mechanism
The scratchpad uses AMD JavaScript (`amd/src/notes.js`) with:
- 1-second debounce on typing
- AJAX calls to `block_codesandbox_save_notes` web service
- Visual feedback ("Typing...", "Saved")

### Real-time Updates
When code execution completes:
1. `mod_codesandbox/callback.php` receives worker results
2. Updates `codesandbox_attempts` (module table)
3. **Updates block tables** (snapshot + history)
4. Frontend auto-refreshes page (800ms delay)
5. Block displays updated data

### Applicable Formats
The block can be added to:
- Course pages (`course-view`)
- Code Sandbox activity pages (`mod-codesandbox-view`)

## Language Support

- English (`lang/en/`)
- Spanish (`lang/es/`)

## Version History

- **v1.0-alpha** (2026-02-13)
  - Initial release
  - Real-time execution monitoring
  - Persistent scratchpad
  - RabbitMQ integration
  - Auto-refresh on completion

## License

Same as Moodle (GPL v3)

## Authors

Developed for Moodle Code Sandbox ecosystem.

---

For more information about the main activity module, see `mod/codesandbox/README.md`.
