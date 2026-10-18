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
- **The Agent's Role:** Verifying and proving that the system functions logically, görsel, and operationally with 100% perfection is **100% the Agent's responsibility**.

---

## 2. DUAL-MODE EXECUTION: DIRECT USER CHAT vs. TASKMANAGER AUTONOMOUS QUEUE

> ### 🛑 OPERATIONAL DIRECTIVE:
> 
> ### MODE A: DIRECT USER CHAT (User Prompts Manually in IDE Window)
> * When the user speaks to you directly in this chat:
> * **PROJECT SCOPE GUARDRAIL:** Verify whether the user's prompt belongs to the active workspace project. If the prompt clearly targets an external/different project (e.g., files, URLs, or components from another repo/directory):
>   - **DO NOT** execute file edits or tasks on the external project immediately.
>   - **WARN THE USER PROMPTLY:** Alert the user that this chat belongs to the current workspace, they may have posted into the wrong window by mistake, and ask if they confirm working on the other project here or wish to switch.
> * If the prompt is for this project (or the user explicitly confirms cross-project work): **USER INSTRUCTION TAKES 100% SUPREMACY.** Address the user's request immediately.
> * **DO NOT SEARCH FOR REST API CALLS OR TASK CODES.** Do not attempt to submit to TaskManager API unless a task code was explicitly given. Complete the work, verify autonomously, and reply directly to the user.
> 
> ### 🛑 STRICT WORKSPACE BOUNDARY & ISOLATION (ZERO EXTERNAL SCANNING):
> * **NO OUT-OF-BOUNDS DIRECTORY ACCESS:** The agent is STRICTLY FORBIDDEN from listing (`list_dir`), reading (`view_file`), grepping, or inspecting any directory or file outside the current project workspace (`c:\wamp64\www\taskmanager`), including neighbor directories (`askerlik`, `ufkayolculuk`, `gutenberg`, `cake`, `C:\wamp64\www\*`).
> * **NO EXTERNAL INSPECTION FOR TESTING:** Even when developing analyzers, deployment tools, or scanners, the agent MUST NOT probe real external user directories. All testing and verification MUST be performed strictly within the workspace using synthetic mock fixtures (e.g., under `tests/fixtures/`).
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
> 3. **Output & Delivery Stage (Task/User Language):** Produce the final task delivery notes (`\Modules\Board\Services\TaskLifecycleService::submitTaskDirectly()` notes), user responses, and test instructions in the language of the task or the creator's default language (e.g., if the task is in Turkish, respond and document in Turkish; if in German, respond in German).

---

## 4. DEFINITION OF DONE (DELIVERY PROTOCOL)
Every completed task must provide an exhaustive summary formatted as follows:
1. **What Was Done (Neler Yapıldı):** Added/modified files, methods, DB schema changes.
2. **Where to Test (Nereden Test Edilir):** Direct testable URLs (e.g., `http://localhost/taskmanager/board?project_id=1`).
3. **How to Test (Nasıl Test Edilir):** Step-by-step credentials, actions, buttons to click, and expected outcomes.
4. **Autonomous Test Evidence (Otonom Test Kanıtı):** Summary of automated headless browser and CLI test results.
5. **Mandatory Architectural Audit Gate (`audit_hmvc.php`):** Before declaring any task finished, submitting a task, or responding to the user, the agent MUST execute `php tests/audit_hmvc.php <ModuleName>` in CLI. If exit code is `1` (any rule violation exists), the agent is STRICTLY FORBIDDEN from stopping or asking the user; it must fix every reported line until the CLI output explicitly states `0 Violations / [PASS]`.

---

## 5. MANDATORY TRI-LINGUAL DEVELOPMENT STANDARD (EN, TR, DE)

> ### 🌐 UNIVERSAL I18N MANDATE:
> **Every single feature, user interface component, button, modal, form input, validation message, API response (`$this->respond(...)`), notification, email bulletin, and system log developed in this project MUST be completely and seamlessly implemented across all three supported languages:**
> 1. **English (`en`):** Default international standard.
> 2. **Turkish (`tr`):** Primary native locale.
> 3. **German (`de`):** Primary European enterprise locale.

### 🚫 STRICT PROHIBITIONS:
1. **NO Hardcoded Strings (Acceptance Criterion #3):** It is strictly prohibited to hardcode any user-facing text, label, tooltip, confirmation prompt, alert dialog, or API response message (such as `$this->respond(false, 'Mesaj metni veya dosya eki zorunludur.');`) in any single language in any View (`Modules/**/Views/**/*.php`), Controller (`Modules/**/Controllers/**/*.php`), Service (`Modules/**/Services/**/*.php`), Model (`Modules/**/Models/**/*.php`), JavaScript file (`Modules/**/Assets/js/**/*.js`), or Component. All strings must be connected to the module's own language dictionary files.
2. **NO Single-Language Fallbacks:** Hardcoded single-language fallbacks (such as `__('ModuleName::key', 'Hardcoded Turkish')`) that ignore the user's active language are strictly forbidden. All fallbacks must dynamically resolve through `Language::current()` or utilize localized default dictionary lookups.
3. **NO Unregistered Inline Scripts in Views:** Placing raw `<script> window.X = {...}; </script>` blocks inside View templates to expose language strings or context data is strictly prohibited. All client-side i18n data MUST be registered via `AssetManager::localizeScript()`.

### 📋 DEVELOPER REQUIREMENTS FOR EVERY TASK:
1. **Module-Isolated Dictionary Files:** Every new or modified translation MUST be defined inside the module's own language files across all 3 languages with 100% identical array keys:
   - `Modules/{ModuleName}/Lang/tr.php`
   - `Modules/{ModuleName}/Lang/en.php`
   - `Modules/{ModuleName}/Lang/de.php`
   - All string lookups MUST strictly use the module namespace syntax: `__('ModuleName::key')` or `$this->lang('ModuleName::key')`.
   - Core dictionaries (`Core/Lang/{tr,en,de}/app.php`) are strictly reserved for global layout elements (Navbar, Sidebar, Command Palette) and must NOT be polluted with module-specific strings.
2. **Client-Side I18n Bridge via AssetManager:** For dynamic JavaScript interactions (prompts, confirms, alerts, modals, fetch requests), controllers or module `init.php` files must register localized data via:

       \Core\AssetManager::localizeScript('handle-name', 'OBJECT_NAME', [
           'key' => __('ModuleName::key')
       ]);

3. **Multi-Channel Language Resolution:** All backend endpoints (REST API, AJAX controllers, SSE streams, Webhooks) must actively honor the 4 language detection channels:
   - URL parameter: `?lang=en|tr|de`
   - HTTP Headers: `Accept-Language` or `X-Language: en|tr|de`
   - Request Body: `{"lang": "en"}`
   - User Profile Preference: `$_SESSION['user']->language` or DB setting.
4. **Mandatory 3-Language Autonomous QA Testing:** Before marking any task as complete or calling `\Modules\Board\Services\TaskLifecycleService::submitTaskDirectly()`, the agent is strictly obligated to autonomously test the feature across all three languages (`?lang=en`, `?lang=tr`, `?lang=de`) in both CLI test scripts and live headless browser sessions to guarantee zero unlocalized strings or missing translation keys.

---

## 6. STRICT MVC ARCHITECTURE & ZERO SPAGHETTI DOCTRINE

> ### 🛑 ZERO SPAGHETTI RULE:
> **All feature development, refactoring, and data handling MUST adhere to a strict Modular Model-View-Controller (HMVC) architecture.**
> **Writing spaghetti code where Views directly communicate with the database/Models, or where Controllers/Services execute raw SQL bypasses, is STRICTLY FORBIDDEN.**

### 🏛️ MANDATORY HMVC FLOW:

    View (UI Presentation) ➔ Controller (Request & Flow Coordinator) ➔ Model (Business Logic & Domain Queries) ➔ Database (Data Layer)
                                                                    ▲
    Database (Result Rows) ➔ Model (Domain Entity / Clean Data) ➔ Controller (Data Preparation) ➔ View (Rendered HTML)

### 🚫 STRICT PROHIBITIONS (THE 4 GOLDEN ACCEPTANCE CRITERIA):
1. **CRITERION 1 - NO Direct Model or Database Access in Views:** View files (`Modules/**/Views/**/*.php`) and UI components CANNOT access Models or the Database directly. There MUST always be a Controller in between. Executing SQL queries, using `$db`, `global $db`, `$this->db`, calling DataLayer/PDO methods, or calling `new ...Model()` / `Model::` inside any View template is strictly prohibited. All view data MUST be prepared by the Controller and injected cleanly.
2. **CRITERION 2 - NO SQL Queries or DB Calls in Controllers/Services:** Controllers (`Modules/**/Controllers/**/*.php`), Services (`Modules/**/Services/**/*.php`), and `init.php` files CANNOT execute SQL queries or DB methods that belong inside a Model (such as `$this->db->exec_query()`, `$this->db->query()`, `$this->db->row_query()`, `$this->db->insert()`, `$this->db->delete()`, `->prepare()`, `SELECT`, `INSERT`, `UPDATE`, `DELETE`). All database queries and mutations MUST come from dedicated Model classes (`Modules/{ModuleName}/Models/{ModelName}.php`).
3. **CRITERION 3 - NO Hardcoded Language Strings Anywhere:** Static Turkish, English, or German strings (such as `$this->respond(false, 'Mesaj metni veya dosya eki zorunludur.');`) CANNOT exist in Controllers, Models, Services, Views, or JS files. Every string must come from the module's own `Lang/{tr,en,de}.php` files via `__('ModuleName::key')`.
4. **CRITERION 4 - NO Inline or In-File CSS and JS Blocks:**
   - View files, Modals, and PHP files CANNOT contain inline `style="..."` attributes or in-file `<style>...</style>` blocks. Every style rule must come from the module's own CSS files under `Modules/{ModuleName}/Assets/css/`.
   - View files, Modals, and PHP files CANNOT contain in-file `<script>...</script>` blocks or inline event attributes (`onclick="..."`, `onchange="..."`, `onsubmit="..."`). Every script and event listener must come from the module's own JS files under `Modules/{ModuleName}/Assets/js/`, with dynamic data passed via `AssetManager::localizeScript()`.
5. **NO Embedded Modals in Page Views:** Modal dialogs must not be embedded directly inside page templates. Every modal MUST reside in its own dedicated file under `Modules/{ModuleName}/Views/modals/{modal_name}.php`.

### 📋 DEVELOPER REQUIREMENTS FOR EVERY VIEW & COMPONENT:
1. **Dedicated Controllers:** Every View template must be backed by a corresponding Controller extending `Core\BaseController` (e.g., `Modules\Board\Controllers\BoardController`).
2. **Dedicated Models:** All database interactions must be abstracted into Model classes extending `Core\BaseModel` (e.g., `Modules\Board\Models\TaskModel`).
3. **Clean Variable Injection:** Controllers must pass clean, typed arrays or objects to Views (e.g., via Controller methods returning structured data extracted into the template).
4. **Automated Gatekeeper Verification:** Every module modification must pass `php tests/audit_hmvc.php <ModuleName>` with zero violations before task completion.

---

## 7. MANDATORY GIT REPOSITORY SYNCHRONIZATION (GITHUB PUSH DOCTRINE)

> ### 🚨 STRICT GIT PUSH DIRECTIVE:
> **After every modification, completed feature, bugfix, or task execution, the agent is STRICTLY OBLIGATED to immediately stage, commit with a descriptive message, and push all changed code to the remote GitHub repository (`origin main` or active branch).**

### 🚫 STRICT PROHIBITIONS:
1. **NO Unpushed or Lingering Commits:** It is strictly prohibited to leave code changes uncommitted or unpushed in the local repository after completing work or marking a task as done.
2. **NO Work Submission Without Push:** Under NO circumstances should `\Modules\Board\Services\TaskLifecycleService::submitTaskDirectly()`, task workflow API submission, or a user response declaring completion be executed before verifying that `git push` succeeded without errors.

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
- **Strict HMVC Architecture:** Direct SQL execution, calling `$db` / `$this->db`, or instantiating Models inside any View (`Modules/**/Views/**/*.php`) or UI Component is strictly forbidden. All database queries must reside in dedicated Model classes extending `Core\BaseModel`, and request orchestration must remain within Controllers extending `Core\BaseController`. Direct SQL execution (`$this->db->exec_query()`, etc.) inside Controllers, Services, or `init.php` is strictly forbidden.
- **Universal Tri-Lingual Standard (i18n):** All UI texts, error/success messages (including `$this->respond(...)` payloads), and system notifications must be managed via module-isolated `Modules/{ModuleName}/Lang/{lang}.php` dictionaries across all 3 languages (TR, EN, DE). Hardcoded single-language strings and inline translation blocks in views are strictly prohibited.
- **HMVC & Modular Isolation Standard (MANDATORY):** All modules in the system must be fully isolated (self-contained) under `Modules/{ModuleName}/`. Without exception, each module must contain the following 5 elements in its own folder:
  1. `module.json` (ID, version, slug, dependencies),
  2. `init.php` (hooks, AssetManager asset and i18n queue entries),
  3. `Lang/{tr,en,de}.php` (All three-language translations of the module in its own folder, resolved using `__('ModuleName::key')`),
  4. `Assets/js/` and `Assets/css/` (Static files belonging to the module, enqueued using `AssetManager::enqueueJs/Css` with data localized via `AssetManager::localizeScript`),
  5. `Controllers/`, `Models/`, `Views/` (Business logic derived from `Core\BaseController` and `Core\BaseModel`).
- **Zero Inline Code Doctrine:**
  - Zero inline CSS (`style="..."` attributes or `<style>` blocks) in Views and Modals; all styles must reside in `Modules/{ModuleName}/Assets/css/`.
  - Zero inline JavaScript (`<script>` logic blocks or `onclick="..."` attributes) in Views and Modals; all client-side data and i18n strings must be exposed via `AssetManager::localizeScript()`.
  - Zero embedded modals in page view templates; all modals must be isolated files in `Modules/{ModuleName}/Views/modals/{modal_name}.php`.
- **Mandatory Automated HMVC Audit:** Before completing any task, the agent MUST run `php tests/audit_hmvc.php <ModuleName>` (or `ALL`) and resolve every reported violation until the output reports `0 Violations / [PASS]`.
- **QA Doctrine:** "The User Is Not a QA Tester". Every button, modal, form input, state transition, and AJAX call must be autonomously verified in the live environment using headless browser, curl, and CLI scripts before task completion.
- **Task Delivery Protocol:** Tasks must be delivered via `\Modules\Board\Services\TaskLifecycleService::submitTaskDirectly()` following the 5-stage Definition of Done (DoD) format (What Was Done, Where to Test, How to Test, Autonomous Test Evidence, HMVC Audit Output).
- **Documentation Access via REST API:** Project documentation must NOT be stored as loose markdown files in the root directory. AI Agents can query all project documentation via `GET http://localhost/taskmanager/api/projects/{code}/docs` and fetch full document contents using `GET http://localhost/taskmanager/api/docs/{id}` with header `Authorization: Bearer <api_token>`.
- **Taskmanager Access Credentials:**
  - Email: `iletisimtktalha@gmail.com`
  - Password: `145236`
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

      $headers = @{ 'Authorization' = 'Bearer tm_admin_talha_keskin_token_2026' } (Invoke-RestMethod -Uri 'http://localhost/taskmanager/api/projects/01/docs' -Headers$headers).data | Select-Object id, title

- **PowerShell (Read Document Content):**

      $headers = @{ 'Authorization' = 'Bearer tm_admin_talha_keskin_token_2026' } (Invoke-RestMethod -Uri 'http://localhost/taskmanager/api/docs/97' -Headers$headers).data.content

- **cURL:**

      curl -s -H "Authorization: Bearer tm_admin_talha_keskin_token_2026" "http://localhost/taskmanager/api/projects/01/docs"

- **Python:**

      import requests
      headers = {"Authorization": "Bearer tm_admin_talha_keskin_token_2026"}
      resp = requests.get("http://localhost/taskmanager/api/projects/01/docs", headers=headers).json()
      for doc in resp.get("data", []):
          print(f"[{doc['id']}] {doc['title']}")