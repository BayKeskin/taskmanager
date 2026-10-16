-- Migration Rollback for TSK-1096 (Ana dizini temizle)
-- Removes the 2 newly inserted docs and rolls back project 1 agent_rules

DELETE FROM docs 
WHERE project_id = 1 
  AND title IN (
    '📋 19. AI Proje Mimarı & Akıllı Görev Ayrıştırma Strateji Planı',
    '⚡ 20. TaskManager Enterprise REST API & Yapay Zeka (AI Agent) Dokümantasyonu'
  );

UPDATE projects 
SET agent_rules = '### [01] Task Manager Project Rules
- **Strict MVC Architecture:** Direct SQL execution or calling `$db` inside any View (`View/**/*.php`) or UI Component (`Components/**/*.php`) is strictly forbidden. All data queries must reside in dedicated `Model` classes, and request coordination must remain within `Controller` classes.
- **Universal Tri-Lingual Standard (i18n):** All UI texts, error/success messages, and system notifications must be managed via `languages/{lang}/app.php` dictionaries across all 3 languages (TR, EN, DE) and `Config/ajax_translations.php`. Hardcoded single-language strings are strictly prohibited.
- **QA Doctrine:** "The User Is Not a QA Tester". Every button, modal, form input, state transition, and AJAX call must be autonomously verified in the live environment using headless browser, curl, and CLI scripts before task completion.
- **Task Delivery Protocol:** Tasks must be delivered via `Helper::submitTaskDirectly()` following the 4-stage Definition of Done (DoD) format (What Was Done, Where to Test, How to Test, Autonomous Test Evidence).'
WHERE id = 1;
