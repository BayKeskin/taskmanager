<?php
namespace Modules\AgentRules\Models;

use Core\BaseModel;

class AgentRulesModel extends BaseModel
{
    private const DEFAULT_GLOBAL_RULES = <<<MARKDOWN
# 🛡️ GLOBAL AGENT EXECUTION DOCTRINE (AGENTS.md)

## 1. THE USER IS NOT A QA TESTER (HUMAN-LIKE LIVE TESTING)
- **Zero Tolerance:** Never tell the user "It's done, please check" without testing it yourself like a real human.
- **No Superficial cURL Checks:** Checking only HTTP 200 via `curl` is STRICTLY FORBIDDEN. You MUST open the live browser, click buttons, submit forms, and inspect both **Console** (0 JS errors, 0 404 assets) and **Network Response Body** (even if HTTP 200 is returned, verify the payload contains NO `xdebug-error`, `Fatal error`, `PDOException`, `Uncaught`, or `Warning`).
- **QA AGENT VALIDATION SUPREMACY:** The QA Agent is NOT the Developer's defense attorney; the QA Agent is the USER'S IMPARTIAL REFEREE. Developer submission notes ("What Was Done") are completely irrelevant. The QA Agent must independently execute and verify the exact end-to-end user scenario in the live browser. If the user's primary intended outcome does not work 100% on screen, approval is STRICTLY FORBIDDEN.

## 2. DUAL-MODE OPERATION (IDE CHAT vs. TASKMANAGER QUEUE)
- **MODE A - Direct IDE Chat:** When the user writes directly in chat without a `task_code`, fulfill the request immediately, verify in the live browser, and reply directly in the user's language. Do NOT call TaskManager submit APIs.
- **MODE B - TaskManager Queue (`task_code` Assigned):** Focus ONLY on the assigned `task_code`. 
  - 🛑 **STRICT REST API MANDATE (NO DATABASE / NO DIRECT FILE ACCESS):** It is STRICTLY FORBIDDEN to search for, inspect, read, or directly write to central taskmanager core files, services, or databases (`taskmanager_db`). Never run custom PHP/SQL scripts to submit tasks.
  - 🚀 **OFFICIAL DELIVERY COMMAND:** Task completion MUST be submitted EXCLUSIVELY via the official HTTP REST API command (`Invoke-RestMethod` / `curl`) provided in your active prompt.
  - 🛑 **END TURN IMMEDIATELY:** Once the REST API call succeeds, **END YOUR TURN IMMEDIATELY**. Never pull or start the next task manually; the autonomous queue will handle it.

## 3. STRICT WORKSPACE ISOLATION (DIRECTORY BOUNDARY)
- You are strictly responsible ONLY for the current project workspace directory.
- **FORBIDDEN:** Never list, read, grep, or modify any file outside the active project root (e.g., neighbor directories under `c:/wamp64/www/*`, and especially the central taskmanager folder).
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

## 6. ANTI-LOOP COMPACTION RULE (CONTEXT COMPACTION MEMORY RESUME)
- **Zero Redundant Introductions:** When waking up from an Antigravity IDE automated context compaction, NEVER re-answer past setup, greeting, or ID request messages (e.g. initial prompts like 'Sen TaskManager Coderisin, bana id ver').
- **Immediate Task Execution:** Instantly ignore past introductory messages upon compaction wake-up, and proceed directly to execute the latest injected active task (task_code) without unnecessary conversational turns.

---
MARKDOWN;

    private const DEFAULT_QA_GLOBAL_INSTRUCTIONS = <<<MARKDOWN
0. USER REQUIREMENT IS SUPREME (ANTI-RUBBERSTAMPING PROTOCOL):
   - Never trust Developer notes. Focus 100% on the User's Original Requirement.
   - Autonomously execute the exact scenario in the live browser (click buttons, submit forms, login).
   - If the user's primary intended outcome does not work on screen, REJECT immediately!
1. LIVE BROWSER FUNCTIONAL TEST:
   - Open target URL in the live browser.
   - Verify forms submit properly, buttons work, UI layout is intact, and no dead elements exist.
2. NETWORK & CONSOLE AUDIT (ZERO TOLERANCE):
   - Console: Verify 0 JS errors and 0 missing 404 assets.
   - Network: Ensure responses contain NO PHP Fatals, Xdebug errors, PDOExceptions, or HTTP 500s.
3. PROJECT-SPECIFIC TEST EXECUTION:
   - If the specific project defines a CLI test or audit command (e.g., PHPUnit, php artisan test, or custom audit), execute only what is defined in the project's own QA instructions.
MARKDOWN;

    /**
     * Genel kural şablonunu settings tablosundan okur, yoksa varsayılanı döner.
     */
    public function getGlobalRules(): string
    {
        $rows = $this->query("SELECT global_agent_rules FROM settings WHERE id = 1 LIMIT 1");
        if (!empty($rows) && !empty($rows[0]->global_agent_rules)) {
            return (string)$rows[0]->global_agent_rules;
        }
        return self::DEFAULT_GLOBAL_RULES;
    }

    /**
     * Genel kural şablonunu kaydeder.
     */
    public function saveGlobalRules(string $content): bool
    {
        $this->query("UPDATE settings SET global_agent_rules = ? WHERE id = 1", [$content]);
        return true;
    }

    /**
     * Genel QA test protokolü şablonunu settings tablosundan okur.
     */
    public function getQaGlobalInstructions(): string
    {
        $rows = $this->query("SELECT qa_global_instructions FROM settings WHERE id = 1 LIMIT 1");
        if (!empty($rows) && !empty($rows[0]->qa_global_instructions)) {
            return (string)$rows[0]->qa_global_instructions;
        }
        return self::DEFAULT_QA_GLOBAL_INSTRUCTIONS;
    }

    /**
     * Genel QA test protokolünü kaydeder.
     */
    public function saveQaGlobalInstructions(string $content): bool
    {
        $this->query("UPDATE settings SET qa_global_instructions = ? WHERE id = 1", [$content]);
        return true;
    }

    /**
     * Tüm projeleri ve kural bilgilerini çeker.
     */
    public function getAllProjectsWithRules(): array
    {
        return $this->query("
            SELECT id, project_code, title, workspace_path, agent_rules, updated_at 
            FROM projects 
            WHERE status != 'archived' 
            ORDER BY id ASC
        ");
    }

    /**
     * Tekil projeyi getirir.
     */
    public function getProjectById(int $id): ?object
    {
        $rows = $this->query("
            SELECT id, project_code, title, workspace_path, agent_rules 
            FROM projects 
            WHERE id = ? 
            LIMIT 1
        ", [$id]);
        return !empty($rows) ? (object)$rows[0] : null;
    }

    /**
     * Projenin çalışma dizini yolunu günceller.
     */
    public function updateWorkspacePath(int $id, string $workspacePath): bool
    {
        $this->query("UPDATE projects SET workspace_path = ? WHERE id = ?", [$workspacePath, $id]);
        return true;
    }

    /**
     * Projenin agent_rules alanını günceller.
     */
    public function updateAgentRules(int $id, string $rules): bool
    {
        $this->query("UPDATE projects SET agent_rules = ? WHERE id = ?", [$rules, $id]);
        return true;
    }

    /**
     * Projenin qa_test_instructions alanını günceller.
     */
    public function updateQaTestInstructions(int $id, string $instructions): bool
    {
        $this->query("UPDATE projects SET qa_test_instructions = ? WHERE id = ?", [$instructions, $id]);
        return true;
    }
}
