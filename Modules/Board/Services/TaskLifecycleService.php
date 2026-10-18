<?php
namespace Modules\Board\Services;

use Core\BaseModel;
use Modules\GithubDevops\Services\GitService;
use Modules\SSE\Services\SseBroadcaster;

/**
 * TaskLifecycleService - Görevlerin durum geçişlerini, otonom teslimatını,
 * kuyruk yönetimini ve webhook tetiklemelerini yönetir.
 */
class TaskLifecycleService extends BaseModel
{
    /**
     * Çift UTF-8 Mojibake temizler
     */
    public static function fixUtf8Mojibake(?string $text): string
    {
        if (empty($text)) {
            return (string)$text;
        }

        static $replacements = [
            'Ä±' => 'ı', 'ÄŸ' => 'ğ', 'Ã¼' => 'ü', 'ÅŸ' => 'ş', 'Ã¶' => 'ö', 'Ã§' => 'ç',
            'Ä°' => 'İ', 'Äž' => 'Ğ', 'Ãœ' => 'Ü', 'Åž' => 'Ş', 'Ã–' => 'Ö', 'Ã‡' => 'Ç',
            'Ã¢' => 'â', 'Ã®' => 'î', 'Ã»' => 'û', 'Ã‚' => 'Â', 'ÃŽ' => 'Î',
            'â€™' => "'", 'â€œ' => '"', 'â€'  => '"', 'â€“' => '–', 'â€”' => '—',
            'ğŸ”‘' => '🔑', 'âš ï¸ ' => '⚠️', 'âš ï¸' => '⚠️', 'âš ' => '⚠️', 'âš¡' => '⚡',
            'âš™ï¸ ' => '⚙️', 'âš™ï¸' => '⚙️', 'âš™' => '⚙️', 'ğŸ§©' => '🧩', 'ğŸ’¾' => '💾',
            'ğŸ”„' => '🔄', 'ğŸš€' => '🚀', 'ğŸ’¡' => '💡', 'ğŸ“ ' => '📝', 'ğŸ“Š' => '📊',
            'ğŸ” ' => '🔍', 'âœ…' => '✅', 'â Œ' => '❌', 'âž¡' => '➡️', 'â¬…' => '⬅️',
            'ğŸ‘ ' => '👍', 'ğŸ”¥' => '🔥', 'ğŸ“¦' => '📦'
        ];

        return strtr($text, $replacements);
    }

    /**
     * Recursively sanitize UTF-8 strings in arrays/objects
     */
    public static function sanitizeUtf8Deep(mixed $data): mixed
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $k => $v) {
                $cleanKey = is_string($k) ? mb_convert_encoding($k, 'UTF-8', 'UTF-8') : $k;
                $cleaned[$cleanKey] = self::sanitizeUtf8Deep($v);
            }
            return $cleaned;
        }
        if (is_object($data)) {
            foreach ($data as $k => $v) {
                $data->$k = self::sanitizeUtf8Deep($v);
            }
            return $data;
        }
        return $data;
    }

    /**
     * Bir görevin Yapay Zeka / Otonom Ajan görevi olup olmadığını denetler.
     */
    public static function isAiTask(mixed $taskOrTags): bool
    {
        if (empty($taskOrTags)) {
            return false;
        }
        $tagString = '';
        if (is_string($taskOrTags)) {
            $tagString = $taskOrTags;
        } elseif (is_object($taskOrTags)) {
            $tagString = $taskOrTags->tags ?? '';
        } elseif (is_array($taskOrTags)) {
            $tagString = $taskOrTags['tags'] ?? '';
        }
        if (empty($tagString) && !is_object($taskOrTags) && !is_array($taskOrTags)) {
            return false;
        }

        $rawTags = !empty($tagString) ? array_map('trim', explode(',', mb_strtolower($tagString))) : [];

        $humanKeywords = ['human', 'insan', 'manual', 'manuel', 'no_ai', 'no-ai'];
        foreach ($rawTags as $tg) {
            $clean = ltrim($tg, '#');
            if (in_array($clean, $humanKeywords, true)) {
                return false;
            }
        }

        $aiKeywords = ['ai', 'otonom', 'agent', 'yapay zeka', 'antigravity'];
        foreach ($rawTags as $tg) {
            $clean = ltrim($tg, '#');
            if (in_array($clean, $aiKeywords, true)) {
                return true;
            }
        }

        $parentId = null;
        if (is_object($taskOrTags) && !empty($taskOrTags->parent_task_id)) {
            $parentId = (int)$taskOrTags->parent_task_id;
        } elseif (is_array($taskOrTags) && !empty($taskOrTags['parent_task_id'])) {
            $parentId = (int)$taskOrTags['parent_task_id'];
        }

        if ($parentId) {
            $db = $GLOBALS['db'] ?? null;
            if (!$db || empty($db->connect)) {
                $db = new \Core\DataLayer();
            }
            $parentTags = (string)$db->single_query("SELECT tags FROM tasks WHERE id = ?", [$parentId]);
            if (!empty($parentTags)) {
                $pRawTags = array_map('trim', explode(',', mb_strtolower($parentTags)));
                foreach ($pRawTags as $ptg) {
                    $pClean = ltrim($ptg, '#');
                    if (in_array($pClean, $aiKeywords, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Antigravity Ajanı tarafından görevi tek hamlede İNCELEMEDE ('submitted') sütununa taşır.
     */
    public static function submitTaskDirectly(int|string $taskIdOrCode, string $notes = 'Görev tamamlandı ve test edildi.'): array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        try {
            $notes = self::fixUtf8Mojibake($notes);

            $task = is_numeric($taskIdOrCode)
                ? $db->row_query("SELECT * FROM tasks WHERE id = ? LIMIT 1", [(int)$taskIdOrCode])
                : $db->row_query("SELECT * FROM tasks WHERE task_code = ? LIMIT 1", [$taskIdOrCode]);

            if (!$task) {
                return ['success' => false, 'error' => 'Task not found'];
            }

            $taskId = (int)$task->id;
            $projectId = (int)$task->project_id;
            $oldStatus = $task->status;

            // 1. Görevi İNCELEMEDE ('submitted') durumuna al
            $db->exec_query("UPDATE tasks SET status = 'submitted', updated_at = NOW() WHERE id = ?", [$taskId]);

            // 1.1 [AI ARCHITECT AUTO-ROLLUP]: Alt görev bittiğinde ve tüm kardeşleri bittiyse Ana Görevi de 'submitted' yap
            if (!empty($task->parent_task_id)) {
                $parentId = (int)$task->parent_task_id;
                $remainingSubs = (int)$db->single_query("
                    SELECT COUNT(*) FROM tasks 
                    WHERE parent_task_id = ? AND status NOT IN ('submitted', 'completed')
                ", [$parentId]);
                
                if ($remainingSubs === 0) {
                    $parentRow = $db->row_query("SELECT id, task_code, title, status FROM tasks WHERE id = ?", [$parentId]);
                    if ($parentRow && $parentRow->status !== 'submitted' && $parentRow->status !== 'completed') {
                        $db->exec_query("UPDATE tasks SET status = 'submitted', updated_at = NOW() WHERE id = ?", [$parentId]);
                        SseBroadcaster::broadcast('task_status_changed', $projectId, $parentId, 1, [
                            'task_id'    => $parentId,
                            'task_code'  => $parentRow->task_code,
                            'title'      => $parentRow->title,
                            'old_status' => $parentRow->status,
                            'new_status' => 'submitted',
                            'user_name'  => 'AI Architect Rollup'
                        ]);
                    }
                }
            }

            // 1.2 [GITHUB DEVOPS & AUTO-PUSH]: Görev tesliminde otomatik commit & push ve commit url üretimi
            $commitHash = null;
            $attachmentUrl = '';
            try {
                $projectObj = $db->row_query("SELECT * FROM projects WHERE id = ? LIMIT 1", [$projectId]);
                if ($projectObj) {
                    $gitRes = GitService::autoCommitAndPushTask($projectObj, $task);
                    if (!empty($gitRes['success'])) {
                        $commitHash = $gitRes['commit_hash'] ?? null;
                        $attachmentUrl = $gitRes['commit_url'] ?? '';
                    }
                }
            } catch (\Throwable $e) {
                error_log("autoCommitAndPushTask error: " . $e->getMessage());
            }

            // 1.3 [SCHEMA DIFF & ROLLBACK SQL]: Otomatik şema farkı ve ajanın .migrations/{task_code}_down.sql kaydı
            $rollbackSql = null;
            $schemaDiffSummary = null;
            try {
                if (!empty($projectObj)) {
                    $diffRes = GitService::computePostTaskSchemaDiff($taskId, $projectObj, $task->task_code);
                    $rollbackSql = !empty($diffRes['rollback_sql']) ? $diffRes['rollback_sql'] : null;
                    $schemaDiffSummary = !empty($diffRes['summary']) ? $diffRes['summary'] : null;
                }
            } catch (\Throwable $e) {
                error_log("computePostTaskSchemaDiff error: " . $e->getMessage());
            }

            // 2. Teslimat kaydı
            $db->exec_query("
                INSERT INTO task_submissions (task_id, submitted_by, notes, attachment_url, commit_hash, rollback_sql, schema_diff_summary, status, created_at)
                VALUES (?, 1, ?, ?, ?, ?, ?, 'pending', NOW())
            ", [$taskId, $notes, $attachmentUrl, $commitHash, $rollbackSql, $schemaDiffSummary]);

            // 3. Aktivite kaydı
            $db->exec_query("
                INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                VALUES (?, 1, 'submitted', ?, 'submitted', ?, NOW())
            ", [$taskId, $oldStatus, "🤖 AI Agent görevi tamamladı ve İncelemede sütununa taşıdı: {$notes}"]);

            // 4. Canlı SSE yayını
            SseBroadcaster::broadcast('task_submitted', $projectId, $taskId, 1, [
                'task_id'    => $taskId,
                'task_code'  => $task->task_code,
                'title'      => $task->title,
                'old_status' => $oldStatus,
                'new_status' => 'submitted',
                'notes'      => $notes,
                'user_name'  => 'AI Agent'
            ]);

            // 5. Dispatcher'daki aktif görev kilidini temizlemek ve 4sn sonra sıradaki görevi tetiklemek için webhook gönder
            \Modules\RestApi\Services\WebhookService::triggerProjectWebhook($projectId, 'task.submitted', [
                'task_id'    => $taskId,
                'task_code'  => $task->task_code,
                'title'      => $task->title,
                'project_id' => $projectId
            ]);

            return [
                'success'   => true,
                'task_id'   => $taskId,
                'task_code' => $task->task_code,
                'status'    => 'submitted',
                'message'   => "Görev [{$task->task_code}] İNCELEMEDE sütununa taşındı. Şimdi başka hiçbir işlem yapmadan işlemin bittiğini özetle ve dur. Sıradaki görev 4 saniye içinde otomatik tetiklenecektir."
            ];
        } catch (\Throwable $e) {
            error_log("submitTaskDirectly error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Otonom Yapay Zeka Kuyruk Yöneticisi:
     */
    public static function dispatchNextAiTaskIfAvailable(int $projectId, ?int $justFinishedTaskId = null): ?array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        try {
            $inProgressSql = "
                SELECT t.* FROM tasks t
                LEFT JOIN tasks p ON t.parent_task_id = p.id
                WHERE t.project_id = ? 
                  AND t.status = 'in_progress'
                  AND NOT EXISTS (SELECT 1 FROM tasks sub WHERE sub.parent_task_id = t.id)
                  AND (
                      (
                          LOWER(t.tags) LIKE '%ai%' 
                          OR LOWER(t.tags) LIKE '%otonom%' 
                          OR LOWER(t.tags) LIKE '%agent%'
                          OR LOWER(t.tags) LIKE '%yapay zeka%'
                          OR LOWER(t.tags) LIKE '%antigravity%'
                      )
                      OR (
                          t.parent_task_id IS NOT NULL 
                          AND (
                              LOWER(p.tags) LIKE '%ai%' 
                              OR LOWER(p.tags) LIKE '%otonom%' 
                              OR LOWER(p.tags) LIKE '%agent%'
                              OR LOWER(p.tags) LIKE '%yapay zeka%'
                              OR LOWER(p.tags) LIKE '%antigravity%'
                          )
                      )
                  )
                ORDER BY t.id ASC
                LIMIT 1
            ";
            $currentInProgress = $db->row_query($inProgressSql, [$projectId]);

            if ($currentInProgress && (int)$currentInProgress->id !== $justFinishedTaskId) {
                if (self::isAiTask($currentInProgress)) {
                    $dispatched = self::triggerTaskStartedWebhook((int)$currentInProgress->id, 'in_progress', ['resumed' => true]);
                    return [
                        'status'     => 'resumed_existing_in_progress',
                        'task_id'    => (int)$currentInProgress->id,
                        'task_code'  => $currentInProgress->task_code,
                        'title'      => $currentInProgress->title,
                        'dispatched' => $dispatched
                    ];
                }
            }

            $sql = "
                SELECT t.* FROM tasks t
                LEFT JOIN tasks p ON t.parent_task_id = p.id
                WHERE t.project_id = ? 
                  AND t.status = 'assigned'
                  AND NOT EXISTS (SELECT 1 FROM tasks sub WHERE sub.parent_task_id = t.id)
                  AND (
                      (
                          LOWER(t.tags) LIKE '%ai%' 
                          OR LOWER(t.tags) LIKE '%otonom%' 
                          OR LOWER(t.tags) LIKE '%agent%'
                          OR LOWER(t.tags) LIKE '%yapay zeka%'
                          OR LOWER(t.tags) LIKE '%antigravity%'
                      )
                      OR (
                          t.parent_task_id IS NOT NULL 
                          AND (
                              LOWER(p.tags) LIKE '%ai%' 
                              OR LOWER(p.tags) LIKE '%otonom%' 
                              OR LOWER(p.tags) LIKE '%agent%'
                              OR LOWER(p.tags) LIKE '%yapay zeka%'
                              OR LOWER(p.tags) LIKE '%antigravity%'
                          )
                      )
                  )
                ORDER BY t.parent_task_id ASC, t.id ASC
            ";
            $candidateTasks = $db->multi_record_query($sql, [$projectId]) ?: [];

            foreach ($candidateTasks as $candidate) {
                if (!self::isAiTask($candidate)) {
                    continue;
                }

                $nextTask = $candidate;
                $taskId = (int)$nextTask->id;

                $db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$taskId]);

                if (!empty($nextTask->parent_task_id)) {
                    $parentId = (int)$nextTask->parent_task_id;
                    $parentRow = $db->row_query("SELECT id, task_code, title, status FROM tasks WHERE id = ?", [$parentId]);
                    if ($parentRow && $parentRow->status !== 'in_progress') {
                        $db->exec_query("UPDATE tasks SET status = 'in_progress', updated_at = NOW() WHERE id = ?", [$parentId]);
                        SseBroadcaster::broadcast('task_status_changed', $projectId, $parentId, 1, [
                            'task_id'    => $parentId,
                            'task_code'  => $parentRow->task_code,
                            'title'      => $parentRow->title,
                            'old_status' => $parentRow->status,
                            'new_status' => 'in_progress',
                            'user_name'  => 'AI Architect Rollup'
                        ]);
                    }
                }

                $db->exec_query("
                    INSERT INTO task_activities (task_id, user_id, action_type, old_value, new_value, comment, created_at)
                    VALUES (?, 1, 'status_change', 'assigned', 'in_progress', '🤖 Otonom Yapay Zeka Kuyruk Yöneticisi sıradaki AI görevini başlattı.', NOW())
                ", [$taskId]);

                SseBroadcaster::broadcast('task_status_changed', $projectId, $taskId, 1, [
                    'task_id'    => $taskId,
                    'task_code'  => $nextTask->task_code,
                    'title'      => $nextTask->title,
                    'old_status' => 'assigned',
                    'new_status' => 'in_progress',
                    'user_name'  => 'Autonomous AI Queue'
                ]);

                $dispatched = self::triggerTaskStartedWebhook($taskId, 'assigned');

                return [
                    'status'     => 'dispatched_next_assigned',
                    'task_id'    => $taskId,
                    'task_code'  => $nextTask->task_code,
                    'title'      => $nextTask->title,
                    'dispatched' => $dispatched
                ];
            }

            return null;
        } catch (\Throwable $e) {
            error_log("dispatchNextAiTaskIfAvailable error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Görev başladı webhook'unu fırlatır
     */
    public static function triggerTaskStartedWebhook(int $taskId, ?string $oldStatus = null, array $extra = []): array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        try {
            $task = $db->row_query("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) {
                return ['sent' => false, 'reason' => 'Task not found'];
            }

            GitService::capturePreTaskSnapshot((int)$task->id);

            if (!self::isAiTask($task)) {
                return ['sent' => false, 'reason' => 'Not an AI task (human task - no ai tag)'];
            }

            $checklists = $db->multi_record_query("
                SELECT id, title, is_completed 
                FROM task_checklists 
                WHERE task_id = ? 
                ORDER BY id ASC
            ", [$taskId]) ?: [];

            $assigneeRows = $db->multi_record_query("
                SELECT u.name 
                FROM task_assignees ta 
                JOIN users u ON ta.user_id = u.id 
                WHERE ta.task_id = ?
            ", [$taskId]) ?: [];
            $assignees = array_map(fn($a) => $a->name, $assigneeRows);

            $isResumed = !empty($extra['resumed']);
            $taskTitle = $isResumed ? "🔄 [RESUMING TASK] " . $task->title : $task->title;

            $desc = $task->description ?: '(No description provided)';

            if (!empty($task->parent_task_id)) {
                $parentTask = $db->row_query("SELECT task_code, title, description FROM tasks WHERE id = ?", [(int)$task->parent_task_id]);
                if ($parentTask) {
                    $desc = "📌 **PARENT TASK ARCHITECTURAL CONTEXT:** [{$parentTask->task_code}] {$parentTask->title}\n"
                          . "📝 **Parent Task Overview:** " . ($parentTask->description ?: '-') . "\n\n"
                          . "🎯 **ACTIVE SUBTASK SCOPE & REQUIREMENTS:**\n" . $desc;
                }
            }

            if ($isResumed) {
                $desc = "🔄 **Previous priority/revision task was completed! Resumed this pending task autonomously.**\n\n" . $desc;
            }

            $attachmentRows = $db->multi_record_query("
                SELECT id, file_name, file_path, file_type, file_size 
                FROM task_attachments 
                WHERE task_id = ? 
                ORDER BY id ASC
            ", [$taskId]) ?: [];

            $baseDir = dirname(__DIR__, 3);
            $apiCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
            $baseUrl = rtrim($apiCfg['base_url'], '/');

            $attachments = [];
            $attachmentsMd = '';
            if (!empty($attachmentRows)) {
                $attachmentsMd = "\n\n### 📎 Task Attachments & Reference Designs:\n"
                    . "> **MANDATORY ASSET INSPECTION:** Carefully inspect all attached visual designs and reference documents before writing any code.\n";
            }

            foreach ($attachmentRows as $row) {
                $relPath = ltrim(str_replace(['\\', '/taskmanager/'], ['/', ''], (string)$row->file_path), '/');
                $cleanAbsPath = str_replace('\\', '/', $baseDir . '/' . $relPath);
                $fileUrl = $baseUrl . '/' . $relPath;

                $fName = $row->file_name ?: basename($relPath);
                $fType = strtolower($row->file_type ?? '');
                $fUri  = 'file:///' . ltrim($cleanAbsPath, '/');
                $isImg = str_starts_with($fType, 'image/') || preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $fName);
                $icon  = $isImg ? '🖼️' : '📄';
                $hint  = $isImg 
                    ? "*(Reference Image: inspect using `view_file` or visual tools to match design pixel-perfect)*" 
                    : "*(Attached Document: view using `view_file`)*";

                $attachmentsMd .= "- {$icon} [{$fName}]({$fUri}) [Web URL: {$fileUrl}] {$hint}\n";

                $attachments[] = [
                    'id'        => (int)$row->id,
                    'file_name' => $row->file_name,
                    'file_type' => $row->file_type,
                    'file_size' => (int)$row->file_size,
                    'file_path' => $row->file_path,
                    'abs_path'  => $cleanAbsPath,
                    'file_uri'  => $fUri,
                    'url'       => $fileUrl,
                    'exists'    => file_exists($cleanAbsPath),
                ];
            }

            if (!empty($attachmentsMd)) {
                $desc .= $attachmentsMd;
            }

            $desc .= "\n\n---\n⚡ **AUTONOMOUS COMPLETION INSTRUCTION (FAST HTTP API - DO NOT SEARCH FOR TASKMANAGER FILES):**\nWhen development and autonomous verification are 100% complete, to prevent the system from deadlocking and allow the queue to immediately proceed to the next pending task, you MUST deliver the task directly via HTTP API by running this command in your shell:\n" . \Modules\Dispatcher\Services\DispatcherService::getAgentSubmitCommandBlock($task->task_code);

            $payloadData = array_merge([
                'task_id'       => (int)$task->id,
                'task_code'     => $task->task_code,
                'title'         => $taskTitle,
                'description'   => $desc,
                'priority'      => $task->priority,
                'deadline'      => $task->deadline,
                'time_estimate' => $task->time_estimate,
                'tags'          => $task->tags,
                'assignees'     => $assignees,
                'checklists'    => $checklists,
                'attachments'   => $attachments,
                'old_status'    => $oldStatus,
                'new_status'    => 'in_progress',
                'resumed'       => $isResumed,
            ], $extra);

            return \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.started', $payloadData);
        } catch (\Throwable $e) {
            error_log("triggerTaskStartedWebhook error: " . $e->getMessage());
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Görev için revizyon talep edildiğinde Ajanı tetikler
     */
    public static function triggerTaskRevisionWebhook(int $taskId, string $reason, array $extra = []): array
    {
        $db = $GLOBALS['db'] ?? null;
        if (!$db || empty($db->connect)) {
            $db = new \Core\DataLayer();
        }

        try {
            $task = $db->row_query("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) return ['sent' => false];

            if (!self::isAiTask($task)) {
                return ['sent' => false, 'reason' => 'Not an AI task (human task - no ai tag)'];
            }

            $checklists = $db->multi_record_query("SELECT id, title, is_completed FROM task_checklists WHERE task_id = ? ORDER BY id ASC", [$taskId]) ?: [];

            $attachmentRows = $db->multi_record_query("
                SELECT id, file_name, file_path, file_type, file_size 
                FROM task_attachments 
                WHERE task_id = ? 
                ORDER BY id ASC
            ", [$taskId]) ?: [];

            $baseDir = dirname(__DIR__, 3);
            $apiCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
            $baseUrl = rtrim($apiCfg['base_url'], '/');

            $attachments = [];
            $attachmentsMd = '';
            if (!empty($attachmentRows)) {
                $attachmentsMd = "\n\n### 📎 Task Attachments & Reference Designs:\n"
                    . "> **MANDATORY ASSET INSPECTION:** Carefully inspect all attached visual designs and reference documents before writing any code.\n";
            }

            foreach ($attachmentRows as $row) {
                $relPath = ltrim(str_replace(['\\', '/taskmanager/'], ['/', ''], (string)$row->file_path), '/');
                $cleanAbsPath = str_replace('\\', '/', $baseDir . '/' . $relPath);
                $fileUrl = $baseUrl . '/' . $relPath;

                $fName = $row->file_name ?: basename($relPath);
                $fType = strtolower($row->file_type ?? '');
                $fUri  = 'file:///' . ltrim($cleanAbsPath, '/');
                $isImg = str_starts_with($fType, 'image/') || preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $fName);
                $icon  = $isImg ? '🖼️' : '📄';
                $hint  = $isImg 
                    ? "*(Reference Image: inspect using `view_file` or visual tools to match design pixel-perfect)*" 
                    : "*(Attached Document: view using `view_file`)*";

                $attachmentsMd .= "- {$icon} [{$fName}]({$fUri}) [Web URL: {$fileUrl}] {$hint}\n";

                $attachments[] = [
                    'id'        => (int)$row->id,
                    'file_name' => $row->file_name,
                    'file_type' => $row->file_type,
                    'file_size' => (int)$row->file_size,
                    'file_path' => $row->file_path,
                    'abs_path'  => $cleanAbsPath,
                    'file_uri'  => $fUri,
                    'url'       => $fileUrl,
                    'exists'    => file_exists($cleanAbsPath),
                ];
            }

            $revDesc = ($task->description ?: '');
            if (!empty($attachmentsMd)) {
                $revDesc .= $attachmentsMd;
            }
            $revDesc .= "\n\n---\n⚡ **AUTONOMOUS COMPLETION INSTRUCTION (FAST HTTP API - DO NOT SEARCH FOR TASKMANAGER FILES):**\nWhen revision fixes and autonomous verification are complete, to prevent the system from deadlocking and allow the queue to immediately proceed to the next pending task, you MUST deliver the task directly via HTTP API by running this command in your shell:\n" . \Modules\Dispatcher\Services\DispatcherService::getAgentSubmitCommandBlock($task->task_code);

            $payloadData = array_merge([
                'task_id'         => (int)$task->id,
                'task_code'       => $task->task_code,
                'title'           => "⚠️ [REVISION REQUEST] " . $task->title,
                'description'     => $revDesc,
                'revision_reason' => $reason,
                'priority'        => $task->priority,
                'checklists'      => $checklists,
                'attachments'     => $attachments,
                'old_status'      => $task->status,
                'new_status'      => 'modification_requested'
            ], $extra);

            return \Modules\RestApi\Services\WebhookService::triggerProjectWebhook((int)$task->project_id, 'task.revision_requested', $payloadData);
        } catch (\Throwable $e) {
            error_log("triggerTaskRevisionWebhook error: " . $e->getMessage());
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }
}
