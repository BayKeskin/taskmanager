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

## 2. STRICT SINGLE-TASK EXECUTION & QUEUE HANDOFF DOCTRINE

> ### 🛑 STRICTEST OPERATIONAL CONSTRAINT (NO TASK JUMPING):
> 1. **Single Active Task Focus:** You must ONLY work on the exact `task_code` explicitly dispatched to you in the current prompt. You are STRICTLY FORBIDDEN from querying the database to jump to older tasks, previously completed tasks, or unrelated tasks!
> 2. **Project Isolation:** You belong exclusively to the project opened in your current workspace directory. Never touch or modify tasks belonging to other projects.
> 3. **Zero Abandonment:** Once a task is in `in_progress` (ÇALIŞILIYOR), you MUST complete all coding and live QA testing before responding to the user. Never leave a task half-done.
> 4. **Clean Handoff Protocol:** When you finish coding and testing the active task:
>    - Run `Helper::submitTaskDirectly('TASK_CODE', 'Detailed DoD notes...')` in the terminal to move the task to `submitted` (İNCELEMEDE).
>    - Write your final summary of what you did for THIS task and **END YOUR TURN**.
>    - Do NOT manually pull or start the next task yourself! The background Dispatcher will automatically trigger you with a clean prompt for the next `#ai` task 4 seconds after you finish.

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

<!-- PROJECT_RULES_START -->
## 7. PROJECT-SPECIFIC AGENT RULES (PROJEYE ÖZEL KURALLAR)

> ### 📌 MANDATORY PROJECT-SPECIFIC CONSTRAINTS:
> The following project-specific technical rules, styling constraints, and workflow directives must be strictly observed for project [01] Task Manager:

### [01] Task Manager Proje Kuralları
- **Mimari:** Strict MVC mimarisi zorunludur. View (`View/**/*.php`) veya bileşenler (`Components/**/*.php`) içinde doğrudan SQL / `$db` sorguları yazmak kesinlikle yasaktır. Tüm veri erişimi `Model` sınıflarında, akış `Controller` sınıflarında olmalıdır.
- **Çok Dilli Standart (i18n):** Tüm arayüz metinleri, hata/başarı mesajları ve bildirimler 3 dilde (TR, EN, DE) `languages/{lang}/app.php` sözlükleri üzerinden yönetilmeli; hardcoded metin yazılmamalıdır.
- **QA Doktrini:** "Kullanıcı test elemanı değildir". Her buton, modal, AJAX çağrısı ve form canlı ortamda otonom test edilmeden teslim edilemez.
- **Görev Teslimi:** İş tamamlandığında `Helper::submitTaskDirectly()` fonksiyonu ile 4 aşamalı DoD (Neler Yapıldı, Nereden Test Edilir, Nasıl Test Edilir, Test Kanıtı) formatında teslim edilmelidir.
<!-- PROJECT_RULES_END -->
