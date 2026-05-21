# 📦 Moodle CodeSandbox - Complete System

> ## ⚠️ **Disclaimer**
> 
> This is a personal experiment I made out of curiosity to see how far it could go. It is not a commercial product. Use it at your own risk in development/test environments.

---

## 🎯 What is this?

**CodeSandbox** is a complete system that turns Moodle into an integrated development environment (IDE) for teaching programming. It lets students write, run, and submit code in real time, while instructors review and grade submissions.

### What makes this different:
- **Runs REAL code** in isolated Docker containers (not just a text editor)
- **Asynchronous architecture** with message queues (RabbitMQ) to handle hundreds of concurrent runs
- **Instant feedback** with execution results (stdout/stderr)
- **Automatic grading** via input/output test cases
- **Real-time monitoring** with execution history

## 📦 Project Components

This repository contains **3 main components** that work together:

### 1️⃣ **mod_codesandbox** - Main Activity
Moodle activity plugin that provides:
- Monaco Editor (VS Code in the browser)
- Submission and status management (draft/submitted/graded)
- Automatic grading system (test cases)
- Teacher review panel
- Multi-language support (Python, JavaScript, Java, C++, etc.)

📂 **Directory:** `mod_codesandbox/`  
📖 **Docs:** See [mod_codesandbox/docs/README.md](mod_codesandbox/docs/README.md)

### 2️⃣ **block_codesandbox** - Monitoring Panel
Complementary sidebar block that shows:
- 📸 **Snapshot of the last run** (code, status, execution time)
- 📊 **Execution history** (last 5 runs with success/failure indicators)
- 📝 **Persistent scratchpad** (auto-saved notes per activity)

📂 **Directory:** `block_codesandbox/`  
📖 **Docs:** See [block_codesandbox/docs/README.md](block_codesandbox/docs/README.md)

### 3️⃣ **the-judgeman-microservice** - Execution Worker
Python microservice that:
- Consumes jobs from RabbitMQ
- Runs code in ephemeral Docker containers
- Applies security limits (timeout, memory, CPU)
- Sends results back to Moodle via HTTP callback

📂 **Directory:** `the-judgeman-microservice/`  
📖 **Docs:** See [the-judgeman-microservice/README.md](the-judgeman-microservice/README.md)

---

## 🚀 Quick Install

### Prerequisites
- Moodle 3.9+ (tested on 4.x)
- Docker and Docker Compose
- RabbitMQ (can be run via Docker)
- PHP 7.4+ with `php-amqplib` extension

### Step by Step

1. **Install the Moodle plugins**
```bash
cd /var/www/html/moodle

# Activity module
cp -r mod_codesandbox mod/codesandbox

# Monitoring block
cp -r block_codesandbox blocks/codesandbox

# Update DB from Moodle UI
# Admin -> Notifications -> Upgrade
```

2. **Configure RabbitMQ**
```bash
docker run -d --name rabbitmq \
  -p 5672:5672 -p 15672:15672 \
  rabbitmq:3-management
```

3. **Deploy the Worker**
```bash
cd the-judgeman-microservice
cp .env.example .env
# Edit .env with RabbitMQ and Moodle credentials
docker-compose up -d
```

4. **Configure the plugin in Moodle**
- Go to: Site administration -> Plugins -> Activities -> Code Sandbox
- Configure:
  - RabbitMQ host/port/credentials
  - Callback token (shared secret with the worker)
  - Allowed languages

See detailed documentation in each component.

---

## ✨ Highlights

### For Students
- ✅ **Professional editor** (Monaco - same as VS Code)
- ✅ **Syntax highlighting** for multiple languages
- ✅ **Instant execution** with real-time feedback
- ✅ **Run history** visible in the sidebar
- ✅ **Persistent scratchpad** for notes
- ✅ **Read-only mode** after submission

### For Teachers
- ✅ **Submission dashboard** with filters and search
- ✅ **Code review** with execution results
- ✅ **Automatic grading** via test cases
- ✅ **Optional manual grading** with comments
- ✅ **Execution stats** per student
- ✅ **Export submissions** (code + results)

### Technical/Security
- ✅ **Asynchronous architecture** with a message queue
- ✅ **Ephemeral containers** (destroyed after each run)
- ✅ **Resource limits** (CPU, memory, timeout)
- ✅ **Network isolation** (no internet access from containers)
- ✅ **Non-root user** in containers
- ✅ **Token-based callbacks** (secure webhook)

---

## 🏗️ System Architecture

The system uses a **decoupled, asynchronous** architecture to ensure Moodle server safety and scalability under hundreds of concurrent runs.

### Data Flow Diagram
`[Student (Browser)]` -> `[Moodle (Plugin)]` -> `[RabbitMQ (Queue)]` -> `[Worker (Consumer)]` -> `[Docker (Sandbox)]`

### 🧩 Main Components

#### 1. Frontend (Moodle Plugin)
* **Technology:** PHP (Moodle API), JavaScript (AMD Modules), Monaco Editor.
* **Responsibility:** Render the code editor (VS Code style).
    * Manage states (`draft`, `submitted`, `graded`).
    * Send execution requests to the queue.
    * Receive results via Webhook/Callback.

#### 2. Message Broker (RabbitMQ)
* **Role:** Request buffer.
* **Responsibility:** Decouple the web request from heavy execution. If 500 students submit at once, RabbitMQ queues them and delivers them to workers in order, preventing the Moodle web server from collapsing.

#### 3. The Worker (The Executor)
* **Technology:** Python / Node.js (Daemon).
* **Responsibility:** Listen to the `moodle_code_jobs` queue.
    * Orchestrate the Docker container lifecycle.
    * **Security:** Enforce time (timeout) and memory limits.
    * Report results back to Moodle.

#### 4. The Sandbox (Docker)
* **Role:** Ephemeral execution environment.
* **How it works:**
    * A **new** container is created per run (`docker run`).
    * Student code is injected.
    * The script runs.
    * `STDOUT` and `STDERR` are captured.
    * The container is immediately destroyed (`--rm`).
    * **Isolation:** No network access (optional), non-privileged user (non-root), read-only filesystem.

---

## 🔄 Detailed Execution Flow (The Lifecycle)

1.  **Submit/Run:** The user clicks "Run". JS sends the code to `run.php`.
2.  **Queueing:** Moodle creates a record in `mdl_codesandbox_attempts` with status `pending` and a unique `job_id`. It sends the payload to RabbitMQ.
3.  **Consume:** The Worker receives a new message.
4.  **Isolation:** The Worker spins up a Docker container for the language (e.g., `python:3.9-alpine`).
5.  **Execution:** Code runs inside the container.
    * *If it takes > 5s:* The Worker kills the container (Timeout).
    * *If it uses too much RAM:* Docker kills it (OOM Kill).
6.  **Callback:** The Worker sends the result (JSON) to Moodle via an internal API (`callback.php`).
7.  **Feedback:** Moodle updates the DB and notifies the user (via WebSocket or AJAX polling) that the result is ready.

---

## 🛡️ Security Measures

Because arbitrary code execution is allowed, security is priority #1:

* **Ephemeral Containers:** No state is saved. Each run is "clean".
* **Non-Root User:** Student code runs as `nobody` or `sandbox` inside the container.
* **Resource Limits:** Docker flags `--memory="128m" --cpus="0.5"`.
* **Timeouts:** Hard execution limit (e.g., 5 seconds) to avoid infinite loops (`while True: pass`).
* **Network Ban:** Containers are launched with `--network none` to prevent local network attacks or malicious downloads.

---

## 🛠️ Recommended Backend Stack
* **Broker:** RabbitMQ (official image `rabbitmq:3-management`).
* **Worker:** Python 3 with `pika` (RabbitMQ client) and `docker` (Docker SDK).
* **Containers:** Alpine Linux (lightweight and fast).

## 🧠 Automatic Grading System (I/O Matching)

The plugin implements a **Level 2: Input/Output Comparison (Black Box Testing)** grading strategy. This methodology evaluates code behavior without analyzing internal syntax, ensuring objectivity and multi-language support.

### ⚙️ Grading Logic
The teacher defines a set of **Test Cases**. Each case includes:
* **Input (stdin):** Data injected into the program (e.g., numbers, text).
* **Expected Output (stdout):** The exact output the program should print.

### 🔄 Evaluation Technical Flow

1.  **Preparation (Moodle):**
    * The teacher creates the activity and adds 3 test cases.
    * When submitting, Moodle builds a JSON payload that includes:
        * `source_code`: The student's script.
        * `test_cases`: `[{ "in": "2 2", "out": "4" }, { "in": "10 5", "out": "15" }]`.

2.  **Iterative Execution (Worker):**
    * The Worker receives the job and starts the Docker container.
    * **Test Loop:** For each test case, the Worker runs the code and injects the `input` through `stdin`.
    * **Capture:** It captures the container's `stdout`.

3.  **Validation and Scoring:**
    * **Normalization:** Trim extra whitespace and newlines (`trim()`).
    * **Comparison:** `Actual Output === Expected Output`.
    * **Calculation:** If the student passes 2 of 3 tests, the Worker computes: `Score = (2/3) * 100 = 66.6%`.

4.  **Feedback (Moodle):**
    * The Worker returns the final score and test details (which passed/failed).
    * Moodle automatically updates the **Gradebook** with the computed score.

### 📊 Payload Example (RabbitMQ)
```json
{
  "job_id": "abc-123",
  "language": "python",
  "code": "a = int(input())\nb = int(input())\nprint(a + b)",
  "test_cases": [
    { "id": 1, "input": "5\n5", "expected_output": "10" },
    { "id": 2, "input": "-1\n1", "expected_output": "0" }
  ]
```
---

## 📁 Repository Structure

```
codesandbox/
├── mod_codesandbox/           # Main activity module
│   ├── view.php               # Student view
│   ├── run.php                # Execution endpoint
│   ├── callback.php           # Result receiver
│   ├── report.php             # Teacher dashboard
│   ├── amd/src/               # JavaScript (AMD modules)
│   ├── classes/               # PHP Classes
│   │   ├── grader.php         # Grading logic
│   │   └── rabbitmq_client.php
│   ├── db/                    # Database schema
│   ├── lang/                  # i18n strings
│   └── vendor/                # Dependencies (Composer)
│
├── block_codesandbox/         # Monitoring sidebar block
│   ├── block_codesandbox.php  # Block logic
│   ├── externallib.php        # Web services
│   ├── amd/src/notes.js       # Scratchpad logic
│   ├── db/                    # DB tables (snapshot, history, notes)
│   └── lang/                  # Translations
│
└── the-judgeman-microservice/ # Execution worker
    ├── src/
    │   ├── main.py            # Entry point
    │   ├── worker.py          # RabbitMQ consumer
    │   ├── docker_manager.py  # Docker orchestration
    │   ├── callback_handler.py
    │   └── config.py
    ├── config.json            # Language configs
    ├── docker-compose.yml     # Multi-container setup
    └── requirements.txt       # Python dependencies
```

---

## 🛠️ Tech Stack

| Component | Technology | Purpose |
|------------|-----------|-----------|
| **Frontend** | Monaco Editor (TypeScript) | VS Code-like code editor |
| **Backend** | PHP 7.4+ (Moodle API) | Business logic and DB |
| **Message Queue** | RabbitMQ 3.x | Async job queue |
| **Worker** | Python 3.9+ (pika, docker-py) | Consumer and executor |
| **Sandbox** | Docker 20.10+ | Ephemeral Linux containers |
| **Database** | PostgreSQL / MySQL | Moodle storage |
| **Communication** | HTTP (webhooks) | Worker -> Moodle callbacks |

---

## 🧪 Use Cases

### Use Case 1: Intro to Python Class
A teacher creates an activity "Sum of two numbers" with 5 test cases. 100 students submit code simultaneously. RabbitMQ queues executions, the worker processes each in ~2 seconds, and everyone gets automatic grading without overloading the Moodle server.

### Use Case 2: Live Algorithms Exam
An exam with 3 programming problems. Students have 90 minutes. The system allows multiple runs, but only the final submission counts. The teacher later reviews each student's code with the full execution history.

### Use Case 3: JavaScript Workshop
A practice activity without auto-grading. Students use the editor to experiment with JavaScript. The sidebar block shows run history so they can see progress. The teacher can review manually and provide feedback.

---

## 🤝 Contributions

This is an experimental personal project. If you find bugs or want to improve it:
1. Fork the repo
2. Create a branch with your feature
3. Open a PR

---

## 📝 License and Credits

- **License:** GNU (use it as you want, but without warranties)  
- **Author:** Mr Jacket
- **Status:** Functional experiment, not a finished product  

**Libraries used:**
- Monaco Editor (Microsoft)
- php-amqplib (Videla/Alvarez)
- Docker SDK for Python
- RabbitMQ (Pivotal/VMware)

---

**⚠️ Final reminder:** This is an experimental project. Use it at your own risk.
