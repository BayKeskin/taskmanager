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
}
