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
            "\xC3\x84\xC2\xB1" => "\xC4\xB1", "\xC3\x84\xC4\x9F" => "\xC4\x9F", "\xC3\x83\xC2\xBC" => "\xC3\xBC",
            "\xC3\x85\xC5\xB8" => "\xC5\x9F", "\xC3\x83\xC2\xB6" => "\xC3\xB6", "\xC3\x83\xC2\xA7" => "\xC3\xA7",
            "\xC3\x84\xC2\xB0" => "\xC4\xB0", "\xC3\x84\xC4\x9E" => "\xC4\x9E", "\xC3\x83\xC5\x93" => "\xC3\x9C",
            "\xC3\x85\xC5\xBD" => "\xC5\x9E", "\xC3\x83\xE2\x80\x93" => "\xC3\x96", "\xC3\x83\xE2\x80\xA1" => "\xC3\x87",
            'Ã¢' => 'â', 'Ã®' => 'î', 'Ã»' => 'û', 'Ã‚' => 'Â', 'ÃŽ' => 'Î',
            'â€™' => "'", 'â€œ' => '"', 'â€'  => '"', 'â€“' => '–', 'â€”' => '—',
            "\xC4\x9F\xC5\xB8\xE2\x80\x9D\xE2\x80\x98" => '🔑', 'âš ï¸ ' => '⚠️', 'âš ï¸' => '⚠️', 'âš ' => '⚠️', 'âš¡' => '⚡',
            'âš™ï¸ ' => '⚙️', 'âš™ï¸' => '⚙️', 'âš™' => '⚙️',
            "\xC4\x9F\xC5\xB8\xC2\xA7\xC2\xA9" => '🧩', "\xC4\x9F\xC5\xB8\xE2\x80\x99\xC2\xBE" => '💾',
            "\xC4\x9F\xC5\xB8\xE2\x80\x9D\xE2\x80\x9E" => '🔄', "\xC4\x9F\xC5\xB8\xC5\xA1\xE2\x82\xAC" => '🚀',
            "\xC4\x9F\xC5\xB8\xE2\x80\x99\xC2\xA1" => '💡', "\xC4\x9F\xC5\xB8\xE2\x80\x9C " => '📝',
            "\xC4\x9F\xC5\xB8\xE2\x80\x9C\xC5\xA0" => '📊', "\xC4\x9F\xC5\xB8\xE2\x80\x9D " => '🔍',
            'âœ…' => '✅', 'â Œ' => '❌', 'âž¡' => '➡️', 'â¬…' => '⬅️',
            "\xC4\x9F\xC5\xB8\xE2\x80\x98 " => '👍', "\xC4\x9F\xC5\xB8\xE2\x80\x9D\xC2\xA5" => '🔥',
            "\xC4\x9F\xC5\xB8\xE2\x80\x9C\xC2\xA6" => '📦'
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
            $taskModel = new \Modules\Board\Models\TaskModel();
            $parentTags = $taskModel->getTaskTags($parentId);
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
    public static function submitTaskDirectly(int|string $taskIdOrCode, string $notes = ''): array
    {
        $taskModel = new \Modules\Board\Models\TaskModel();
        $projectModel = new \Modules\Projects\Models\ProjectModel();

        if ($notes === '') {
            $notes = __('Board::task_completed_tested');
        }

        try {
            $notes = self::fixUtf8Mojibake($notes);

            $task = $taskModel->findByIdOrCode($taskIdOrCode);
            if (!$task) {
                return ['success' => false, 'error' => 'Task not found'];
            }

            $taskId = (int)$task->id;
            $projectId = (int)$task->project_id;
            $oldStatus = $task->status;

            // 1. Görevi İNCELEMEDE ('submitted') durumuna al
            $taskModel->updateStatus($taskId, 'submitted');

            // 1.1 [AI ARCHITECT AUTO-ROLLUP]: Alt görev bittiğinde ve tüm kardeşleri bittiyse Ana Görevi de 'submitted' yap
            if (!empty($task->parent_task_id)) {
                $parentId = (int)$task->parent_task_id;
                $remainingSubs = $taskModel->getRemainingSubtasksCount($parentId);
                
                if ($remainingSubs === 0) {
                    $parentRow = $taskModel->findByIdOrCode($parentId);
                    if ($parentRow && $parentRow->status !== 'submitted' && $parentRow->status !== 'completed') {
                        $taskModel->updateStatus($parentId, 'submitted');
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
                $projectObj = $projectModel->getProjectById($projectId);
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
            $taskModel->recordSubmission($taskId, 1, $notes, $attachmentUrl, $commitHash, $rollbackSql, $schemaDiffSummary);

            // 3. Aktivite kaydı
            $activityMsg = __('Board::activity_ai_submitted', ['notes' => $notes]);
            $taskModel->logActivity($taskId, 1, 'submitted', $oldStatus, 'submitted', $activityMsg);

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

            // 5. Dispatcher'daki aktif görev kilidini temizlemek ve QA / sıradaki görevi tetiklemek için webhook gönder
            $attachmentRows = $taskModel->getTaskAttachments($taskId);
            $baseDir = dirname(__DIR__, 3);
            $apiCfg = \Modules\RestApi\Services\RestApiService::getDynamicApiConfig();
            $baseUrl = rtrim($apiCfg['base_url'], '/');

            $attachments = [];
            foreach ($attachmentRows as $row) {
                $rawPath = str_replace('\\', '/', (string)$row->file_path);
                $relPath = ltrim(preg_replace('#^/?(?:[a-zA-Z0-9_-]+/)?(Public/.*)$#i', '$1', $rawPath), '/');
                $cleanAbsPath = str_replace('\\', '/', $baseDir . '/' . $relPath);
                $fileUrl = $baseUrl . '/' . $relPath;
                $fUri = 'file:///' . ltrim($cleanAbsPath, '/');

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

            \Modules\RestApi\Services\WebhookService::triggerProjectWebhook($projectId, 'task.submitted', [
                'task_id'          => $taskId,
                'task_code'        => $task->task_code,
                'title'            => $task->title,
                'description'      => (string)($task->description ?? ''),
                'attachments'      => $attachments,
                'project_id'       => $projectId,
                'submission_notes' => $notes
            ]);

            return [
                'success'   => true,
                'task_id'   => $taskId,
                'task_code' => $task->task_code,
                'status'    => 'submitted',
                'message'   => __('Board::task_submitted_summary_msg', ['code' => $task->task_code])
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
        $taskModel = new \Modules\Board\Models\TaskModel();

        try {
            // Çift ajanlı (Dev + QA) projelerde, QA incelemesi bekleyen (submitted / under_review)
            // bir görev varsa, QA onaylamadan (completed olmadan) yeni görev çekilemez!
            $projectModel = new \Modules\Projects\Models\ProjectModel();
            $project = $projectModel->getProjectById($projectId);
            if ($project && !empty(trim($project->qa_conversation_id ?? ''))) {
                if ($taskModel->hasPendingQaReviewTasks($projectId)) {
                    return [
                        'status'  => 'waiting_for_qa_approval',
                        'message' => 'Waiting for QA specialist review decision before starting next task'
                    ];
                }
            }

            $currentInProgress = $taskModel->getInProgressAiTask($projectId);

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

            $candidateTasks = $taskModel->getCandidateAiTasks($projectId);

            foreach ($candidateTasks as $candidate) {
                if (!self::isAiTask($candidate)) {
                    continue;
                }

                $nextTask = $candidate;
                $taskId = (int)$nextTask->id;

                $taskModel->updateTaskStatusAndTimestamp($taskId, 'in_progress');

                if (!empty($nextTask->parent_task_id)) {
                    $parentId = (int)$nextTask->parent_task_id;
                    $parentRow = $taskModel->getTaskParentRow($parentId);
                    if ($parentRow && $parentRow->status !== 'in_progress') {
                        $taskModel->updateTaskStatusAndTimestamp($parentId, 'in_progress');
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

                $activityNote = __('Board::autonomous_queue_started_task');
                $taskModel->insertAutonomousActivity($taskId, $activityNote);

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
        $taskModel = new \Modules\Board\Models\TaskModel();

        try {
            $task = $taskModel->getTaskRowById($taskId);
            if (!$task) {
                return ['sent' => false, 'reason' => 'Task not found'];
            }

            GitService::capturePreTaskSnapshot((int)$task->id);

            if (!self::isAiTask($task)) {
                return ['sent' => false, 'reason' => 'Not an AI task (human task - no ai tag)'];
            }

            $checklists = $taskModel->getTaskChecklists($taskId);
            $assignees = $taskModel->getTaskAssigneeNames($taskId);

            $isResumed = !empty($extra['resumed']);
            $taskTitle = $isResumed ? "🔄 [RESUMING TASK] " . $task->title : $task->title;

            $desc = $task->description ?: '(No description provided)';

            if (!empty($task->parent_task_id)) {
                $parentTask = $taskModel->getParentTaskContext((int)$task->parent_task_id);
                if ($parentTask) {
                    $desc = "📌 **PARENT TASK ARCHITECTURAL CONTEXT:** [{$parentTask->task_code}] {$parentTask->title}\n"
                          . "📝 **Parent Task Overview:** " . ($parentTask->description ?: '-') . "\n\n"
                          . "🎯 **ACTIVE SUBTASK SCOPE & REQUIREMENTS:**\n" . $desc;
                }
            }

            if ($isResumed) {
                $desc = "🔄 **Previous priority/revision task was completed! Resumed this pending task autonomously.**\n\n" . $desc;
            }

            $attachmentRows = $taskModel->getTaskAttachments($taskId);

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
                $rawPath = str_replace('\\', '/', (string)$row->file_path);
                $relPath = ltrim(preg_replace('#^/?(?:[a-zA-Z0-9_-]+/)?(Public/.*)$#i', '$1', $rawPath), '/');
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
        $taskModel = new \Modules\Board\Models\TaskModel();

        try {
            $task = $taskModel->getTaskRowById($taskId);
            if (!$task) return ['sent' => false];

            if (!self::isAiTask($task)) {
                return ['sent' => false, 'reason' => 'Not an AI task (human task - no ai tag)'];
            }

            $checklists = $taskModel->getTaskChecklists($taskId);
            $attachmentRows = $taskModel->getTaskAttachments($taskId);

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
                $rawPath = str_replace('\\', '/', (string)$row->file_path);
                $relPath = ltrim(preg_replace('#^/?(?:[a-zA-Z0-9_-]+/)?(Public/.*)$#i', '$1', $rawPath), '/');
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

    /**
     * QA Ajanının onay veya ret kararını işler (Dev + QA Döngüsü & Circuit Breaker)
     *
     * @param int $taskId
     * @param string $decision 'approved' veya 'rejected'
     * @param string $feedback İnceleme notu / hata raporu
     * @param int $userId İncelemeyi yapan kullanıcı ID
     * @param string $userName İncelemeyi yapan kullanıcı adı
     * @return array Sonuç durumu
     */
    public static function handleQaReviewDecision(int $taskId, string $decision, string $feedback, int $userId = 1, string $userName = 'QA Inspector'): array
    {
        $taskModel = new \Modules\Board\Models\TaskModel();
        $task = $taskModel->getTaskRowById($taskId);
        if (!$task) {
            return ['success' => false, 'message' => 'Task not found'];
        }

        if ($decision === 'approved') {
            // Görev onaylandı: Sayacı sıfırla, görevi tamamla, sıradaki görevi tetikle
            $taskModel->resetQaRevisionCount($taskId);
            $taskModel->updateTaskStatus($taskId, 'completed');
            $taskModel->logTaskActivity($taskId, $userId, 'approved', "QA Approved: {$feedback}");

            // Sıradaki AI görevini tetikle
            self::dispatchNextAiTaskIfAvailable((int)$task->project_id, $taskId);

            return [
                'success' => true,
                'status'  => 'completed',
                'action'  => 'approved',
                'message' => 'Task approved and completed successfully'
            ];
        }

        // Karar 'rejected' ise sayacı kontrol et (Circuit Breaker)
        $currentCount = $taskModel->incrementQaRevisionCount($taskId);

        if ($currentCount > 3) {
            // Maksimum revizyon limiti aşıldı! Sonsuz döngüyü kır:
            // Durumu 'in_review' / 'under_review' olarak kilitle ve insan müdahalesine devret
            $taskModel->updateTaskStatus($taskId, 'in_review');
            $circuitBreakerMsg = "🚨 [CIRCUIT BREAKER TRIGGERED] Maximum QA revision limit (3/3) exceeded. Locked in review for human intervention. Last QA feedback: {$feedback}";
            $taskModel->logTaskActivity($taskId, $userId, 'circuit_breaker', $circuitBreakerMsg);

            return [
                'success'         => true,
                'status'          => 'in_review',
                'action'          => 'circuit_breaker_locked',
                'revision_count'  => $currentCount,
                'message'         => 'Maximum revision limit reached. Task locked for human review.'
            ];
        }

        // Limit aşılmadı: Durumu modification_requested yap ve Dev Ajanına revizyon webhooku fırlat
        $taskModel->updateTaskStatus($taskId, 'modification_requested');
        $taskModel->logTaskActivity($taskId, $userId, 'rejected', "QA Rejected (#{$currentCount}/3): {$feedback}");

        $revResult = self::triggerTaskRevisionWebhook($taskId, $feedback, [
            'qa_revision_count' => $currentCount,
            'max_revisions'     => 3
        ]);

        return [
            'success'         => true,
            'status'          => 'modification_requested',
            'action'          => 'revision_dispatched',
            'revision_count'  => $currentCount,
            'webhook_result'  => $revResult,
            'message'         => "Revision #{$currentCount}/3 requested and sent to Dev Agent."
        ];
    }
}
