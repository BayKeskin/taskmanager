# 🛡️ GLOBAL AGENT EXECUTION DOCTRINE (AGENTS.md)

## 1. THE USER IS NOT A QA TESTER (HUMAN-LIKE LIVE TESTING)
- **Zero Tolerance:** Never tell the user "It's done, please check" without testing it yourself like a real human.
- **No Superficial cURL Checks:** Checking only HTTP 200 via `curl` is STRICTLY FORBIDDEN. You MUST open the live browser, click buttons, submit forms, and inspect both **Console** (0 JS errors, 0 404 assets) and **Network Response Body** (even if HTTP 200 is returned, verify the payload contains NO `xdebug-error`, `Fatal error`, `PDOException`, `Uncaught`, or `Warning`).

## 2. DUAL-MODE OPERATION (IDE CHAT vs. TASKMANAGER QUEUE)
- **MODE A - Direct IDE Chat:** When the user writes directly in chat without a `task_code`, fulfill the request immediately, verify in the live browser, and reply directly in the user's language. Do NOT call TaskManager submit APIs.
- **MODE B - TaskManager Queue (`task_code` Assigned):** Focus ONLY on the assigned `task_code`. When finished and verified, submit the task via the official TaskManager API/Service, then **END YOUR TURN IMMEDIATELY**. Never pull the next task manually.

## 3. STRICT WORKSPACE ISOLATION (DIRECTORY BOUNDARY)
- You are strictly responsible ONLY for the current project workspace directory.
- **FORBIDDEN:** Never list, read, grep, or modify any file outside the active project root (e.g., neighbor directories under `c:\wamp64\www\*`).
- If the user's prompt references another project/directory, **STOP** and ask the user for explicit confirmation before touching anything outside.

## 4. TASK DELIVERY FORMAT (DEFINITION OF DONE)
Always reply in the **user's/task's language** (e.g., Turkish) using this exact 4-point structure:
1. **What Was Done (Neler Yapıldı):** Modified/added files, methods, and DB changes.
2. **Where to Test (Nereden Test Edilir):** Exact testable URLs.
3. **How to Test (Nasıl Test Edilir):** Step-by-step user actions and expected results.
4. **Live Test & Audit Evidence (Test Kanıtı):** Browser Network/Console verification + CLI Audit output.

## 5. GIT PUSH & DATABASE ROLLBACK (`.migrations/{task_code}_down.sql`)
- **GitHub Sync:** If a remote Git repo is configured, stage, commit (`feat:`, `fix:`, `refactor:`), and `git push` all changes before marking any task complete.
- **Surgical Rollback:** For every DB schema/data change (`CREATE`, `ALTER`, `INSERT`, `UPDATE`, `DELETE`), generate the exact reverse SQL in `.migrations/{task_code}_down.sql` using safe `IF EXISTS` clauses.

---

<!-- PROJECT_RULES_START -->
## 7. PROJECT-SPECIFIC AGENT RULES

> ### 📌 MANDATORY PROJECT-SPECIFIC CONSTRAINTS:
> The following project-specific technical rules, styling constraints, and workflow directives must be strictly observed for project [01] Task Manager:

PROJECT-SPECIFIC RULES: [01] Task Manager

1. **Strict HMVC Isolation (`Modules/{ModuleName}/`):**
   - Every module must be 100% self-contained with its own `module.json`, `init.php`, `Controllers/`, `Models/`, `Views/`, `Lang/{tr,en,de}.php`, and `Assets/{css,js}/`.
   - **Rule 1 (View Isolation):** Views (`Views/**/*.php`) CANNOT call Models (`new ...Model`, `Model::`) or Database (`$db`, `$this->db`, SQL) directly. Data must come from the Controller.
   - **Rule 2 (Controller/Service SQL Ban):** Controllers, Services, and `init.php` CANNOT execute SQL or DB methods (`$this->db->`, `exec_query`, `row_query`, `query`, `prepare`, `SELECT`, `INSERT`, `UPDATE`, `DELETE`). All DB logic MUST be inside `Models/*Model.php` extending `Core\BaseModel`.
2. **100% Tri-Lingual i18n (TR, EN, DE) & Zero Hardcoded Strings:**
   - **Rule 3 (No Hardcoded Text):** Never write hardcoded Turkish/English strings in Controllers (`$this->respond(...)`), Models, Views, or JS files. Every string must exist in `Modules/{ModuleName}/Lang/{tr,en,de}.php` with identical keys, called via `__('ModuleName::key')` in PHP and `AssetManager::localizeScript()` in JS.
3. **Zero Inline CSS/JS & Modal Isolation:**
   - **Rule 4 (No Inline Code):** Views/PHP files CANNOT contain `<style>`, `<script>`, inline `style="..."`, or `onclick="..."` attributes. All CSS/JS must be in `Modules/{ModuleName}/Assets/{css,js}/` and loaded via `AssetManager`. Modals must be separate files in `Views/modals/`.
4. **NO Hardcoded `/taskmanager/` Base Paths:**
   - **Rule 5 (Dynamic Base URL/Path):** Hardcoding `/taskmanager/` in any PHP, View, or JS file (URLs, fetch, redirects, asset paths) is STRICTLY FORBIDDEN! Always use dynamic base URL helpers (`BASE_URL`, `SITE_URL`, `window.APP_CONFIG.baseUrl`, or relative routing) so the system works in any root or subfolder.
5. **Zero Manual Routing in `Core/AjaxRouter.php` (Pure Reflection Discovery):**
   - **Rule 6 (Core/AjaxRouter Immutability):** Never edit or add manual action arrays/aliases to `Core/AjaxRouter.php`! The core router uses Reflection to automatically discover all public methods in every `Modules/{ModuleName}/Controllers/AjaxController.php`. Define your AJAX action as a public method in your module's own AjaxController.
6. **Zero Hardcoded Machine/User Paths (Portability):**
   - **Rule 7 (Portability):** Never hardcode machine-specific or environment-specific paths (e.g. `C:/Users/DELL`, `wamp64`, `C:/Python...`). Always use dynamic environment variables (`%USERPROFILE%`, `%LOCALAPPDATA%`, `getenv('SystemDrive')`, `window.APP_CONFIG.projectRoot`) or relative paths so the project runs on any machine without modification.
7. **Mandatory Gatekeeper Audit (`taskmanager-audit/audit.php`):**
   - Before completing any work on a module, you MUST run: `php taskmanager-audit/audit.php <ModuleName>` (or `ALL`).
   - Do NOT create or modify files inside `taskmanager-audit/`. If the script outputs `[FAIL]`, fix every reported line until you get `0 Violations / [PASS]`.
8. **Docs & Credentials:**
   - Query project docs via REST API (`GET /api/projects/01/docs` with `Bearer tm_admin_talha_keskin_token_2026`).
   - Test Login: `iletisimtktalha@gmail.com` / `145236`
<!-- PROJECT_RULES_END -->
