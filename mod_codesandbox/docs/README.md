# Code Sandbox for Moodle

> **⚠️ Disclaimer:** Proyecto experimental hecho por curiosidad. Úsalo bajo tu propio riesgo.

**A secure, scalable code execution environment designed for modern programming education.**

## 🎯 Overview

Code Sandbox is a Moodle activity module that transforms your LMS into a professional development environment for students and an efficient code review platform for instructors. Think of it as a hybrid between a programming assignment and an interactive coding challenge—students write, test, and submit code directly in the browser, while instructors can review actual code execution results and assign grades.

## 🏗️ Architecture

This module is part of a **decoupled microservices architecture** designed for security and scalability:

```
┌─────────────────────┐
│   Moodle (this mod) │  ← Student writes code in Monaco Editor
└──────────┬──────────┘
           │
           ▼
    ┌──────────────┐
    │   RabbitMQ   │  ← Job queue (asynchronous processing)
    └──────┬───────┘
           │
           ▼
    ┌────────────────┐
    │ Worker Process │  ← Executes code in isolated Docker containers
    │ (judgeman.py)  │
    └────────┬───────┘
             │
             ▼
    ┌──────────────────┐
    │  Moodle Callback │  ← Results sent back to update attempt records
    └──────────────────┘
```

### Key Components:

1. **Moodle Plugin (this repository)**: Manages the student experience, stores attempts, and handles grading.
2. **RabbitMQ**: Ensures scalability by queuing code execution requests.
3. **Worker Service**: External Python service that reads from the queue, spawns Docker containers, and executes student code.
4. **Docker Containers**: Isolated, secure sandboxes where untrusted code runs safely.
5. **Block CodesAndbox** (`/blocks/codesandbox`): Companion sidebar block that displays real-time execution monitoring and persistent scratchpad notes.

## ✨ Features

### For Students:
- **Professional Code Editor**: Powered by Monaco Editor (the same engine behind VS Code)
- **Multiple Programming Languages**: Python, JavaScript, Java, C++, and more
- **Instant Feedback**: Test code before final submission
- **Read-only Mode**: After submission, code becomes read-only to ensure integrity

### For Instructors:
- **Submission Dashboard**: Review all student submissions with timestamps
- **Live Code Review**: See exactly what students wrote and the execution results
- **Integrated Grading**: Grades sync directly with Moodle's gradebook
- **Execution Insights**: View stdout, stderr, exit codes, and execution time

### Security & Performance:
- **Isolated Execution**: Code runs in ephemeral Docker containers, not on the main server
- **Queue-based Processing**: RabbitMQ prevents server overload during peak times (e.g., exams)
- **Token-based Callbacks**: Secure communication between worker and Moodle
- **Resource Limits**: Configurable CPU/memory limits per execution

## � Integration with block_codesandbox

The activity module works seamlessly with the **CodeSandbox Block** companion plugin:

### How They Connect

When code execution completes, the callback endpoint (`callback.php`) updates **both**:
- **Module tables** (`codesandbox_attempts`) - Stores full execution results
- **Block tables** (`block_codesandbox_snap`, `block_codesandbox_hist`) - Provides real-time monitoring data

### What the Block Provides

1. **📸 Last Run Snapshot**: Preview of most recent code execution with status and runtime
2. **📊 Execution History**: Timeline of last 5 runs with success/failure indicators  
3. **📝 Persistent Scratchpad**: Auto-saving notepad tied to each activity instance

### Data Flow

```
[Student clicks "Run"] 
    ↓
[mod_codesandbox/run.php creates attempt & publishes to RabbitMQ]
    ↓
[Worker executes code in Docker container]
    ↓
[Worker POSTs results to callback.php]
    ↓
[callback.php updates:]
    • codesandbox_attempts (module data)
    • block_codesandbox_snap (snapshot for block)
    • block_codesandbox_hist (history entry for block)
    ↓
[Frontend auto-refreshes page after 800ms]
    ↓
[Block displays updated snapshot + history]
```

### Installation

The block is **optional** but recommended. To install:
```bash
cd /path/to/moodle/blocks
cp -r block_codesandbox codesandbox
php admin/cli/upgrade.php
```

Then add the block to any Code Sandbox activity page.

For detailed block documentation, see `/blocks/codesandbox/README.md`

## 📦 Installation

### Prerequisites

1. **Moodle 4.0+** (tested on Moodle 4.0-4.5)
2. **PHP 7.4+** with `php-bcmath` and `php-xml` extensions
3. **Composer** (for PHP dependencies)
4. **RabbitMQ Server** (running and accessible)
5. **Worker Service** (separate deployment, see [Worker Setup](#worker-setup))
6. **block_codesandbox** (optional, recommended for enhanced UI)

### Steps

1. **Clone or download** this repository into your Moodle installation:
   ```bash
   cd /path/to/moodle/mod
   git clone <repository-url> codesandbox
   ```

2. **Install PHP dependencies**:
   ```bash
   cd codesandbox
   composer install --no-dev
   ```

3. **Install the plugin** via Moodle's web interface:
   - Navigate to **Site Administration → Notifications**
   - Follow the upgrade prompts

4. **Configure RabbitMQ settings**:
   - Go to **Site Administration → Plugins → Activity modules → Code Sandbox**
   - Set:
     - RabbitMQ Host (e.g., `localhost` or `rabbitmq.example.com`)
     - RabbitMQ Port (default: `5672`)
     - RabbitMQ Username (e.g., `moodle`)
     - RabbitMQ Password
     - Queue Name (e.g., `moodle_code_jobs`)

5. **Deploy the Worker Service** (see next section)

## 🔧 Worker Setup

The worker is a **separate Python service** that executes student code. It is not included in this repository.

**What you need:**

- A server with Docker installed
- Python 3.8+ with `pika` (RabbitMQ client library)
- Network access to both RabbitMQ and the Moodle callback endpoint

**How it works:**

1. The worker listens to the RabbitMQ queue (`moodle_code_jobs`)
2. When a job arrives, it:
   - Pulls the appropriate Docker image (e.g., `python:3.11-slim`)
   - Runs the student's code inside the container with resource limits
   - Captures stdout, stderr, and exit code
   - Posts results back to Moodle via `/mod/codesandbox/callback.php`

**Example Worker Architecture:**
```bash
# Worker daemon (e.g., judgeman.py)
while true:
    job = rabbitmq.consume()
    result = docker.run(job.language, job.code, limits)
    http.post(job.callback_url, result, token=job.callback_token)
```

For a complete worker implementation, refer to the separate worker repository or contact the maintainers.

## 🚀 Usage

### Creating a Code Sandbox Activity

1. Turn editing on in your Moodle course
2. Click **Add an activity or resource → Code Sandbox**
3. Configure:
   - **Name**: e.g., "Python Assignment 1"
   - **Language**: Select the programming language
   - **Maximum Grade**: Set the gradebook value
   - **Due Date**: Optional deadline
   - **Instructions**: Provide context and requirements

4. Save the activity

### Student Workflow

1. Open the Code Sandbox activity
2. Write code in the Monaco editor
3. Click **Test** to run the code (creates a draft attempt)
4. View output in the terminal panel
5. When satisfied, click **Submit** (locks the code)

### Instructor Workflow

1. Open the Code Sandbox activity
2. Click **View Student Submissions** (visible only to instructors)
3. Review submitted code and execution results
4. Assign grades directly from the submission dashboard
5. Grades sync to the Moodle gradebook automatically

## 📊 Database Schema

### `mdl_codesandbox`
Main activity instances.

| Field         | Type          | Description                          |
|---------------|---------------|--------------------------------------|
| id            | BIGINT        | Primary key                          |
| course        | BIGINT        | Course ID                            |
| name          | VARCHAR(255)  | Activity name                        |
| intro         | TEXT          | Activity description                 |
| language      | VARCHAR(50)   | Programming language (python, java…) |
| grade         | DECIMAL(10,5) | Maximum grade                        |
| duedate       | BIGINT        | Due date (Unix timestamp)            |
| timecreated   | BIGINT        | Creation timestamp                   |
| timemodified  | BIGINT        | Last modified timestamp              |

### `mdl_codesandbox_attempts`
Student code submissions and execution results.

| Field          | Type          | Description                              |
|----------------|---------------|------------------------------------------|
| id             | BIGINT        | Primary key                              |
| codesandboxid  | BIGINT        | Foreign key to mdl_codesandbox           |
| userid         | BIGINT        | Student user ID                          |
| code           | TEXT          | Student's code                           |
| status         | VARCHAR(20)   | pending, completed, failed, submitted    |
| job_id         | VARCHAR(100)  | Unique job identifier for RabbitMQ       |
| stdout         | TEXT          | Standard output from execution           |
| stderr         | TEXT          | Standard error from execution            |
| exitcode       | INT           | Process exit code                        |
| execution_time | DECIMAL(10,3) | Execution time in seconds                |
| timecreated    | BIGINT        | Attempt creation timestamp               |
| timemodified   | BIGINT        | Last update timestamp                    |

## 🔐 Security Considerations

- **No direct code execution on Moodle server**: All code runs in isolated Docker containers
- **Token-based callback authentication**: Worker must provide a valid HMAC token
- **Resource limits**: Docker containers enforce CPU, memory, and time constraints
- **Read-only submissions**: Once submitted, student code cannot be modified
- **Capability-based access**: Teachers and students have distinct permissions

## 🛠️ Configuration Options

| Setting              | Default           | Description                                |
|----------------------|-------------------|--------------------------------------------|
| `rabbitmq_host`      | `localhost`       | RabbitMQ server hostname                   |
| `rabbitmq_port`      | `5672`            | RabbitMQ AMQP port                         |
| `rabbitmq_user`      | `moodle`          | RabbitMQ username                          |
| `rabbitmq_pass`      | `securepass`      | RabbitMQ password                          |
| `rabbitmq_queue`     | `moodle_code_jobs`| Queue name for code execution jobs         |

Access these via: **Site Administration → Plugins → Activity modules → Code Sandbox**

## 🌐 Internationalization

This plugin supports multiple languages using Moodle's language pack system:

- **English** (`lang/en/codesandbox.php`)
- **Spanish** (`lang/es/codesandbox.php`)

To add more languages, create a new language file in `lang/<langcode>/codesandbox.php` following the same structure.

## 📝 Development Roadmap

- [x] **Phase 1 (MVP)**: Monaco editor integration, multi-language support, draft attempts
- [x] **Phase 2 (Connectivity)**: RabbitMQ integration, asynchronous job processing
- [x] **Phase 3 (Worker)**: External worker service with Docker execution
- [x] **Phase 4 (Evaluation)**: Instructor dashboard for reviewing and grading submissions
- [ ] **Phase 5 (Advanced)**: Unit test support, custom test cases, plagiarism detection

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the **GNU GPL v3** (or later) to comply with Moodle's license requirements.

## 🙏 Credits

- **Monaco Editor**: Microsoft Corporation
- **php-amqplib**: Maintained by the PHP AMQP community
- **Moodle**: Martin Dougiamas and the Moodle community

## 📧 Support

For bug reports, feature requests, or general questions:

- Open an issue on GitHub
- Contact: [your-email@example.com]

---

**Made with ❤️ for the education community**