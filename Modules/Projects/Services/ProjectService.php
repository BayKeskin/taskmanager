<?php
namespace Modules\Projects\Services;

use Core\BaseModel;

/**
 * ProjectService - Proje kuralları senkronizasyonu, şablon üretimi ve çalışma alanı operasyonlarını yönetir.
 */
class ProjectService extends BaseModel
{
    /**
     * Projeye özel kuralları (agent_rules) projenin yerel klasöründeki AGENTS.md ve GEMINI.md dosyalarına senkronize eder.
     */
    public static function syncProjectRulesToWorkspace(object|int $project): array
    {
        $projectModel = new \Modules\Projects\Models\ProjectModel();

        if (is_numeric($project)) {
            $project = $projectModel->getProjectById((int)$project);
        }

        if (!$project || empty($project->workspace_path)) {
            return ['success' => false, 'error' => __('Projects::invalid_workspace_path')];
        }

        $wsPath = rtrim(str_replace('\\', '/', $project->workspace_path), '/');
        if (!is_dir($wsPath)) {
            return ['success' => false, 'error' => __('Projects::workspace_not_found')];
        }

        $rules = trim($project->agent_rules ?? '');
        $projectCode = $project->project_code ?? '';
        $ruleNumber = 7;
        $targetFile = $wsPath . '/AGENTS.md';
        if (file_exists($targetFile)) {
            $existing = file_get_contents($targetFile);
            if (preg_match('/##\s*(\d+)\.\s*PROJECT-SPECIFIC\s+AGENT\s+RULES/i', $existing, $m)) {
                $ruleNumber = (int)$m[1];
            }
        }

        $projectTitle = $project->title ?? '';
        $sectionHeader = "<!-- PROJECT_RULES_START -->\n## {$ruleNumber}. PROJECT-SPECIFIC AGENT RULES\n\n> ### 📌 MANDATORY PROJECT-SPECIFIC CONSTRAINTS:\n> The following project-specific technical rules, styling constraints, and workflow directives must be strictly observed for project [{$projectCode}] {$projectTitle}:\n\n";
        $sectionFooter = "\n<!-- PROJECT_RULES_END -->";
        $customSection = $sectionHeader . ($rules ?: "*(No project-specific rules defined yet)*") . $sectionFooter;

        $targetFiles = ['AGENTS.md'];
        if (file_exists($wsPath . '/GEMINI.md')) {
            $targetFiles[] = 'GEMINI.md';
        }
        $updatedFiles = [];

        foreach ($targetFiles as $fname) {
            $fpath = $wsPath . '/' . $fname;
            if (file_exists($fpath)) {
                $content = file_get_contents($fpath);
                if (str_contains($content, '<!-- PROJECT_RULES_START -->')) {
                    $pattern = '/<!-- PROJECT_RULES_START -->.*?<!-- PROJECT_RULES_END -->/s';
                    $newContent = preg_replace($pattern, $customSection, $content);
                } else {
                    $newContent = rtrim($content) . "\n\n" . $customSection . "\n";
                }
                file_put_contents($fpath, $newContent);
                $updatedFiles[] = $fname;
            } else {
                $baseContent = self::getUniversalAgentTemplate($projectCode, $projectTitle);
                $newContent = str_replace('<!-- PROJECT_RULES_START -->', $customSection, $baseContent);
                file_put_contents($fpath, $newContent);
                $updatedFiles[] = $fname;
            }
        }

        return [
            'success'       => true,
            'updated_files' => $updatedFiles,
            'workspace'     => $wsPath
        ];
    }

    /**
     * Harici projeler için evrensel AGENTS.md şablonu
     */
    public static function getUniversalAgentTemplate(string $projectCode = '', string $projectTitle = ''): string
    {
        return <<< 'MARKDOWN'
# 🛡️ PROJECT AGENT INSTRUCTIONS & EXECUTION DOCTRINE (AGENTS.md)

## 0. CORE DOCTRINE: "THE USER IS NOT A QA TESTER"

> ### 🚨 RED LINE RULE:
> Before an engineer or AI agent tells the user "It's done, please check", "Try it now", or marks any task as completed:
> **The agent is STRICTLY OBLIGATED to autonomously test and verify all screens, every button, every form step, UI visual flaws, and all state transitions in the LIVE ENVIRONMENT using headless browser, curl, PHP/CLI scripts, and API tests.**

Under NO circumstances shall the user ever encounter:
1. A blank or white screen (`Blank Screen`),
2. An unresolved PHP/SQL/JS error, syntax error, warning, or crash,
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
> * **USER INSTRUCTION TAKES 100% SUPREMACY.** Address the user's request immediately.
> * **DO NOT SEARCH FOR REST API CALLS OR TASK CODES.** Do not attempt to submit to TaskManager API unless a task code was explicitly given. Simply complete the work, test autonomously, and reply directly to the user.
> 
> ### MODE B: TASKMANAGER AUTONOMOUS DISPATCH (Prompt Contains Task Code)
> * When triggered by TaskManager background queue with an assigned `task_code`:
> * Focus EXCLUSIVELY on that single task. Never jump to older tasks.
> * 🛑 **STRICT REST API MANDATE (NO DATABASE / NO DIRECT FILE ACCESS):** It is STRICTLY FORBIDDEN to search for, inspect, read, or directly write to TaskManager core files (`c:\wamp64\www\taskmanager\*`), PHP services, or databases (`taskmanager_db`). Never run custom PHP/SQL scripts to inspect or submit tasks.
> * 🚀 **OFFICIAL DELIVERY COMMAND:** Task completion MUST be submitted EXCLUSIVELY via the official HTTP REST API command (`Invoke-RestMethod` / `curl`) provided in your active prompt.
> * 🛑 **END TURN IMMEDIATELY:** Once the REST API call succeeds, **END YOUR TURN IMMEDIATELY**. Never pull or start the next task manually; the autonomous queue will handle it.

---

## 3. PROJECT-NATIVE LANGUAGE & ARCHITECTURE RESPECT
1. **Respect Native Framework:** Adhere strictly to THIS project's existing framework and directory conventions. Never import or assume architectural patterns from external systems.
2. **Project-Native Language:** Follow the language naturally used in THIS project and task. Do NOT force multi-language dictionaries unless explicitly requested by this project's specific rules.
3. **Preserve Codebase Integrity:** Preserve existing code conventions, folder structures, and comments.

---

## 4. DEFINITION OF DONE (DELIVERY PROTOCOL)
Every completed task must provide an exhaustive summary formatted as follows:
1. **What Was Done (Neler Yapıldı):** Added/modified files, methods, DB schema changes.
2. **Where to Test (Nereden Test Edilir):** Direct testable local or live URLs.
3. **How to Test (Nasıl Test Edilir):** Step-by-step credentials, actions, buttons to click, and expected outcomes.
4. **Autonomous Test Evidence (Otonom Test Kanıtı):** Summary of automated headless browser, curl, or CLI test results.

---

## 5. MANDATORY GIT REPOSITORY SYNCHRONIZATION (GITHUB PUSH DOCTRINE)

> ### 🚨 STRICT GIT PUSH DIRECTIVE:
> **After every modification, completed feature, bugfix, or task execution, the agent is STRICTLY OBLIGATED to immediately stage, commit with a descriptive message, and push all changed code to the remote repository (`origin <active-branch>`).**

---

## 6. MANDATORY DATABASE MIGRATION & ROLLBACK DOCTRINE (.migrations/{task_code}_down.sql)

> ### 🗄️ DATABASE ROLLBACK INTEGRITY DIRECTIVE:
> **Whenever an agent creates or modifies database structures (CREATE TABLE, ALTER TABLE) or performs data mutations (INSERT, UPDATE, DELETE) during a task:**
> **The agent is STRICTLY OBLIGATED to generate an inverse rollback SQL script inside the project directory: `.migrations/{task_code}_down.sql`.**

<!-- PROJECT_RULES_START -->
MARKDOWN;
    }

    /**
     * Projeyi ve projeye ait tüm görev, doküman, checklist ve ilişkili verileri siler.
     */
    public static function purgeProject(int $projectId): bool
    {
        $projectModel = new \Modules\Projects\Models\ProjectModel();
        return $projectModel->purgeProject($projectId);
    }
}

