# 🛡️ PROJECT AGENT INSTRUCTIONS & EXECUTION DOCTRINE (AGENTS.md)

## 0. CORE DOCTRINE: "THE USER IS NOT A QA TESTER"

> ### 🚨 RED LINE RULE:
> Before an engineer or AI agent (Antigravity) tells the user "It's done, please check", "Try it now", or marks any task as completed:
> **The agent is STRICTLY OBLIGATED to autonomously test and verify all screens, every button, every form step, UI visual flaws, and all state transitions in the LIVE ENVIRONMENT using headless browser, curl, PHP/CLI scripts, and API tests.**

Under NO circumstances shall the user ever encounter:
1. A blank or white screen (`Blank Screen`),
2. An unresolved PHP/SQL error, syntax error, warning, or crash (`Fatal error`, `Warning`, `Notice`),
3. An unresponsive, unclickable, or dead button,
4. Console JavaScript errors or network 404/500 failures,
5. Misaligned typography, poor borders, clipped elements, or responsive layout defects,
6. Ambiguous deliverables where what was done or how to test is unclear.

---

## 1. USER'S ROLE vs. AGENT'S ROLE
- **The User's Role:** The user is NOT a QA/test employee. Finding missing parts, broken buttons, or bugs is NEVER the user's responsibility. The user only reviews high-level product vision and user experience.
- **The Agent's Role:** Verifying and proving that the system functions logically, visually, and operationally with 100% perfection is **100% the Agent's responsibility**.

---

## 2. DUAL-MODE EXECUTION: DIRECT USER CHAT vs. TASKMANAGER AUTONOMOUS QUEUE

> ### 🛑 OPERATIONAL DIRECTIVE:
> 
> ### MODE A: DIRECT USER CHAT (User Prompts Manually in IDE Window)
> * When the user speaks to you directly in this chat:
> * **USER INSTRUCTION TAKES 100% SUPREMACY.** Address the user's request, questions, or refactorings immediately.
> * **DO NOT SEARCH FOR REST API CALLS OR TASK CODES.** Do not attempt to submit to TaskManager API unless a task code was explicitly given. Complete the work, verify autonomously, and reply directly to the user.
> 
> ### MODE B: TASKMANAGER AUTONOMOUS DISPATCH (Prompt Contains Task Code)
> * When triggered by TaskManager background queue with an assigned `task_code`:
> * Focus EXCLUSIVELY on that single task (`task_code`). Never jump to older or unrelated tasks.
> * When complete and autonomously verified, execute the official REST API submit command provided in the dispatch prompt, then **END YOUR TURN**. Do NOT manually pull or start the next task yourself; the background Dispatcher will automatically trigger you for the next queued task in 4 seconds.

---

## 3. MULTILINGUAL PIPELINE PROTOCOL (INPUT ➔ RULES ➔ OUTPUT)

> ### 🌐 TRI-STAGE LANGUAGE SPECIFICATION:
> 1. **Input Stage (Task Language):** Accept and comprehend user requests and task descriptions in whatever language they are written (Turkish, German, English, etc.) without altering their semantic intent.
> 2. **Rule & Guardrail Execution Stage (English Doctrine):** Internalize, reason, and execute all development rules, QA checks, architectural principles, and safety constraints strictly in English for maximal precision and prompt compliance.
> 3. **Output & Delivery Stage (Task/User Language):** Produce the final task delivery notes (`Helper::submitTaskDirectly` notes), user responses, and test instructions in the language of the task or the creator's default language (e.g., if the task is in Turkish, respond and document in Turkish; if in German, respond in German).

---

## 4. DEFINITION OF DONE (DELIVERY PROTOCOL)
Every completed task must provide an exhaustive summary formatted as follows:
1. **What Was Done (Neler Yapıldı):** Added/modified files, methods, DB schema changes.
2. **Where to Test (Nereden Test Edilir):** Direct testable URLs (e.g., `http://localhost/taskmanager/board?project_id=1`).
3. **How to Test (Nasıl Test Edilir):** Step-by-step credentials, actions, buttons to click, and expected outcomes.
4. **Autonomous Test Evidence (Otonom Test Kanıtı):** Summary of automated headless browser and CLI test results.

---

## 5. MANDATORY TRI-LINGUAL DEVELOPMENT STANDARD (EN, TR, DE)

> ### 🌐 UNIVERSAL I18N MANDATE:
> **Every single feature, user interface component, button, modal, form input, validation message, API response, notification, email bulletin, and system log developed in this project MUST be completely and seamlessly implemented across all three supported languages:**
> 1. **English (`en`):** Default international standard.
> 2. **Turkish (`tr`):** Primary native locale.
> 3. **German (`de`):** Primary European enterprise locale.

### 🚫 STRICT PROHIBITIONS:
1. **NO Hardcoded Strings:** It is strictly prohibited to hardcode any user-facing text, label, tooltip, confirmation prompt, alert dialog, or error message in any single language (especially Turkish or English) in any View (`View/**/*.php`), Controller (`Controller/**/*.php`), JavaScript file (`Public/js/**/*.js`), or Component (`Components/**/*.php`).
2. **NO Single-Language Fallbacks:** Hardcoded single-language fallbacks (such as `__('key', 'Hardcoded Turkish')`) that ignore the user's active language are strictly forbidden. All fallbacks must dynamically resolve through `Language::current()` or utilize localized default dictionary lookups.

### 📋 DEVELOPER REQUIREMENTS FOR EVERY TASK:
1. **Synchronized Dictionary Updates:** When introducing any new UI string, status label, or message, the key MUST be simultaneously added to all 3 dictionary files:
   - `languages/en/*.php`
   - `languages/tr/*.php`
   - `languages/de/*.php`
   - `Config/ajax_translations.php` (for AJAX responses)
   - `Config/api_translations.php` (for REST API responses)
2. **Client-Side I18n Bridge:** For dynamic JavaScript interactions (prompts, confirms, alerts, modals, fetch requests), views must expose a localized data object (e.g., `window.PAGE_I18N = { ... }`) rendered via `__('group.key')` or use global fetch headers with `X-Language: CURRENT_LANG`.
3. **Multi-Channel Language Resolution:** All backend endpoints (REST API, AJAX controllers, SSE streams, Webhooks) must actively honor the 4 language detection channels:
   - URL parameter: `?lang=en|tr|de`
   - HTTP Headers: `Accept-Language` or `X-Language: en|tr|de`
   - Request Body: `{"lang": "en"}`
   - User Profile Preference: `$_SESSION['user']->language` or DB setting.
4. **Mandatory 3-Language Autonomous QA Testing:** Before marking any task as complete or calling `Helper::submitTaskDirectly`, the agent is strictly obligated to autonomously test the feature across all three languages (`?lang=en`, `?lang=tr`, `?lang=de`) in both CLI test scripts and live headless browser sessions to guarantee zero unlocalized strings or missing translation keys.

---

## 6. STRICT MVC ARCHITECTURE & ZERO SPAGHETTI DOCTRINE

> ### 🛑 ZERO SPAGHETTI RULE:
> **All feature development, refactoring, and data handling MUST adhere to a strict Model-View-Controller (MVC) architecture.**
> **Writing spaghetti code where Views directly communicate with the database is STRICTLY FORBIDDEN.**

### 🏛️ MANDATORY MVC FLOW:
```
View (UI Presentation) ➔ Controller (Request & Flow Coordinator) ➔ Model (Business Logic & Database Queries) ➔ Database (Data Layer)
                                                                ▲
Database (Result Rows) ➔ Model (Domain Entity / Clean Data) ➔ Controller (Data Preparation) ➔ View (Rendered HTML)
```

### 🚫 STRICT PROHIBITIONS:
1. **NO Direct Database Calls in Views:** It is strictly prohibited to execute SQL queries, use `$db`, `global $db`, or call DataLayer methods (`$db->query()`, `$db->multi_record()`, `$db->single_record()`, `$db->count_record()`, `$db->row_query()`) inside any View file (`View/**/*.php`) or UI component (`Components/**/*.php`).
2. **NO Business Logic in Views:** Views are solely presentation templates. Formatting, data calculation, metrics aggregation, authorization checks, and database access MUST reside in Controllers and Models.
3. **NO Direct Controller-to-SQL Bypasses:** Controllers must NOT contain raw inline SQL queries. All SQL statements, table operations, and entity relationships must be encapsulated within dedicated Model classes (`Model/*Model.php`).

### 📋 DEVELOPER REQUIREMENTS FOR EVERY VIEW & COMPONENT:
1. **Dedicated Controllers:** Every View template must be backed by a corresponding Controller (e.g., `BoardController`, `TaskController`, `CalendarController`, `ReportController`, `TeamController`, `DocController`, `ChatController`, `SettingsController`, `AuthController`).
2. **Dedicated Models:** All database interactions must be abstracted into Model classes extending `Model` (e.g., `TaskModel`, `ProjectModel`, `UserModel`, `ReportModel`, `DocModel`, `SettingsModel`).
3. **Clean Variable Injection:** Controllers must pass clean, typed arrays or objects to Views (e.g., via Controller methods returning structured data extracted into the template).

---

## 7. MANDATORY GIT REPOSITORY SYNCHRONIZATION (GITHUB PUSH DOCTRINE)

> ### 🚨 STRICT GIT PUSH DIRECTIVE:
> **After every modification, completed feature, bugfix, or task execution, the agent is STRICTLY OBLIGATED to immediately stage, commit with a descriptive message, and push all changed code to the remote GitHub repository (`origin main` or active branch).**

### 🚫 STRICT PROHIBITIONS:
1. **NO Unpushed or Lingering Commits:** It is strictly prohibited to leave code changes uncommitted or unpushed in the local repository after completing work or marking a task as done.
2. **NO Work Submission Without Push:** Under NO circumstances should `Helper::submitTaskDirectly()`, task workflow API submission, or a user response declaring completion be executed before verifying that `git push` succeeded without errors.

### 📋 DEVELOPER REQUIREMENTS FOR EVERY TASK:
1. **Atomic & Descriptive Commits:** Every task or logical change must be committed using standard conventional commit formatting (e.g., `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`).
2. **Immediate Push to Remote:** Execute `git push origin <branch>` immediately after committing changes.
3. **Verification of Remote State:** Confirm that `git status` reports `Your branch is up to date with 'origin/...'` and the working tree is completely clean.

---

## 8. MANDATORY DATABASE MIGRATION & ROLLBACK DOCTRINE (.migrations/{task_code}_down.sql)

> ### 🗄️ DATABASE ROLLBACK INTEGRITY DIRECTIVE:
> **Whenever an agent creates or modifies database structures (CREATE TABLE, ALTER TABLE) or performs data mutations (INSERT, UPDATE, DELETE) during a task:**
> **The agent is STRICTLY OBLIGATED to generate an inverse rollback SQL script inside the project directory: `.migrations/{task_code}_down.sql`.**

### 🚫 STRICT PROHIBITIONS:
1. **NO Unrecorded DB Migrations:** It is strictly prohibited to execute structural database modifications without documenting their exact reverse SQL statements in `.migrations/{task_code}_down.sql`.
2. **NO Destructive Drop Without Verification:** Rollback scripts must use safe IF EXISTS clauses (e.g., `DROP TABLE IF EXISTS table_name;`, `ALTER TABLE table_name DROP COLUMN column_name;`).

### 📋 DEVELOPER REQUIREMENTS FOR EVERY TASK:
1. **Inverse SQL Mapping:** For every `CREATE TABLE`, the down script must specify `DROP TABLE IF EXISTS`. For every `ADD COLUMN`, `DROP COLUMN`. For test data insertions, `DELETE FROM`.
2. **Commit Along with Code:** The `.migrations/{task_code}_down.sql` file must be committed and pushed to GitHub along with the task's code changes.

---

<!-- PROJECT_RULES_START -->
## 9. PROJECT-SPECIFIC AGENT RULES

> ### 📌 MANDATORY PROJECT-SPECIFIC CONSTRAINTS:
> The following project-specific technical rules, styling constraints, and workflow directives must be strictly observed for project [01] Task Manager:

### [01] Task Manager Project Rules
- **Strict MVC Architecture:** Direct SQL execution or calling `$db` inside any View (`View/**/*.php`) or UI Component (`Components/**/*.php`) is strictly forbidden. All data queries must reside in dedicated `Model` classes, and request coordination must remain within `Controller` classes.
- **Universal Tri-Lingual Standard (i18n):** All UI texts, error/success messages, and system notifications must be managed via `languages/{lang}/app.php` dictionaries across all 3 languages (TR, EN, DE) and `Config/ajax_translations.php`. Hardcoded single-language strings are strictly prohibited.
- **QA Doctrine:** "The User Is Not a QA Tester". Every button, modal, form input, state transition, and AJAX call must be autonomously verified in the live environment using headless browser, curl, and CLI scripts before task completion.
- **Task Delivery Protocol:** Tasks must be delivered via `Helper::submitTaskDirectly()` following the 4-stage Definition of Done (DoD) format (What Was Done, Where to Test, How to Test, Autonomous Test Evidence).
- **Documentation Access via REST API:** Project documentation must NOT be stored as loose markdown files in the root directory. AI Agents can query all project documentation via `GET http://localhost/taskmanager/api/projects/{code}/docs` and fetch full document contents using `GET http://localhost/taskmanager/api/docs/{id}` with header `Authorization: Bearer <api_token>`.
<!-- PROJECT_RULES_END -->

---

## 10. PROJECT DOCUMENTATION ACCESS VIA REST API (AI AGENT GUIDELINE)

> ### 📖 REST API DOCUMENTATION RETRIEVAL DIRECTIVE:
> All technical specifications, architecture blueprints, system credentials, and workflow manuals are centrally managed in TaskManager and exposed via the Enterprise REST API.
> **DO NOT keep loose markdown documentation files in the project root directory.** If an AI Agent needs to read project documentation, query them directly via the REST API endpoints:

### 🔌 REST API Endpoints:
1. **List All Project Documents:**
   - **Method & URL:** `GET http://localhost/taskmanager/api/projects/{project_id_or_code}/docs`
   - **Alternative:** `GET http://localhost/taskmanager/api/docs?project={project_code}`
   - **Headers:** `Authorization: Bearer tm_admin_talha_keskin_token_2026` (or `X-API-Key: tm_admin_talha_keskin_token_2026`)
   - **Response Payload:** Returns an array of document items containing `id`, `title`, `icon`, `created_at`, and `updated_at`.

2. **Fetch Document Content by ID:**
   - **Method & URL:** `GET http://localhost/taskmanager/api/docs/{doc_id}`
   - **Headers:** `Authorization: Bearer tm_admin_talha_keskin_token_2026`
   - **Response Payload:** Returns the document object including the complete markdown `content`.

### 💻 Fast Retrieval CLI Examples:
- **PowerShell (List Docs for Project 01):**
  ```powershell
  $headers = @{ 'Authorization' = 'Bearer tm_admin_talha_keskin_token_2026' }
  (Invoke-RestMethod -Uri 'http://localhost/taskmanager/api/projects/01/docs' -Headers $headers).data | Select-Object id, title
  ```
- **PowerShell (Read Document Content):**
  ```powershell
  $headers = @{ 'Authorization' = 'Bearer tm_admin_talha_keskin_token_2026' }
  (Invoke-RestMethod -Uri 'http://localhost/taskmanager/api/docs/97' -Headers $headers).data.content
  ```
- **cURL:**
  ```bash
  curl -s -H "Authorization: Bearer tm_admin_talha_keskin_token_2026" "http://localhost/taskmanager/api/projects/01/docs"
  ```
- **Python:**
  ```python
  import requests
  headers = {"Authorization": "Bearer tm_admin_talha_keskin_token_2026"}
  resp = requests.get("http://localhost/taskmanager/api/projects/01/docs", headers=headers).json()
  for doc in resp.get("data", []):
      print(f"[{doc['id']}] {doc['title']}")
  ```



