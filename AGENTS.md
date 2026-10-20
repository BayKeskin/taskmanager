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
## 6. PROJECT-SPECIFIC RULES: [01] Task Manager

### [01] Task Manager Project Rules
- **Strict MVC Architecture:** Direct SQL execution or calling `$db` inside any View (`View/**/*.php`) or UI Component (`Components/**/*.php`) is strictly forbidden. All data queries must reside in dedicated `Model` classes, and request coordination must remain within `Controller` classes.
- **Universal Tri-Lingual Standard (i18n):** All UI texts, error/success messages, and system notifications must be managed via `languages/{lang}/app.php` dictionaries across all 3 languages (TR, EN, DE) and `Config/ajax_translations.php`. Hardcoded single-language strings are strictly prohibited.
- **QA Doctrine:** "The User Is Not a QA Tester". Every button, modal, form input, state transition, and AJAX call must be autonomously verified in the live environment using headless browser, curl, and CLI scripts before task completion.
- **Task Delivery Protocol:** Tasks must be delivered via `Helper::submitTaskDirectly()` following the 4-stage Definition of Done (DoD) format (What Was Done, Where to Test, How to Test, Autonomous Test Evidence).
- **Documentation Access via REST API:** Project documentation must NOT be stored as loose markdown files in the root directory. AI Agents can query all project documentation via `GET http://localhost/taskmanager/api/projects/{code}/docs` and fetch full document contents using `GET http://localhost/taskmanager/api/docs/{id}` with header `Authorization: Bearer <api_token>`.
- **Taskmanager access user
email iletisimtktalha@gmail.com
password 145236
<!-- PROJECT_RULES_END -->
