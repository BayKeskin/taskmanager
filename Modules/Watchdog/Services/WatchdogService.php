<?php
namespace Modules\Watchdog\Services;

use Helper;
use Database;

/**
 * WatchdogService - Otonom Kurtarma & Kuyruk Denetim Servisi
 * Tıkanan veya birden fazla 'in_progress' kalan AI görevlerini denetler ve kuyruğu canlı tutar.
 */
class WatchdogService
{
    public static function runInspection(): array
    {
        $restartSignal = dirname(__DIR__, 3) . '/Modules/Dispatcher/Bin/restart.signal';
        if (file_exists($restartSignal)) {
            @unlink($restartSignal);
            @file_put_contents(dirname(__DIR__, 3) . '/Modules/Dispatcher/Bin/restarted.log', date('Y-m-d H:i:s') . " - Dispatcher restarting by watchdog signal...\n", FILE_APPEND);
            @pclose(@popen('start "" /b cmd /c "taskkill /F /IM pythonw.exe & timeout /t 1 & cscript //nologo c:\wamp64\www\taskmanager\dispatcher\start_silent.vbs"', 'r'));
        }

        global $db;
        $results = [];

        try {
            if (!$db || empty($db->connect)) {
                require_once dirname(__DIR__, 3) . '/Core/DataLayer.php';
                $db = new \Database();
            }

            $projects = $db->multi_record_query("SELECT id, project_code, title FROM projects WHERE status = 'active'") ?: [];
            foreach ($projects as $prj) {
                $pId = (int)$prj->id;
                
                $allInProgress = $db->multi_record_query("
                    SELECT * FROM tasks 
                    WHERE project_id = ? AND status = 'in_progress'
                    ORDER BY 
                        CASE priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'normal' THEN 3 
                            WHEN 'low' THEN 4 
                            ELSE 5 
                        END ASC,
                        updated_at DESC
                ", [$pId]) ?: [];

                $runningAiTasks = array_values(array_filter($allInProgress, fn($t) => \Modules\Board\Services\TaskLifecycleService::isAiTask($t)));

                if (count($runningAiTasks) >= 1) {
                    $primaryTask = $runningAiTasks[0];
                    if (count($runningAiTasks) > 1) {
                        for ($i = 1; $i < count($runningAiTasks); $i++) {
                            $extra = $runningAiTasks[$i];
                            $db->exec_query("UPDATE tasks SET status = 'assigned' WHERE id = ?", [$extra->id]);
                            \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', $pId, (int)$extra->id, 1, [
                                'task_id'    => (int)$extra->id,
                                'task_code'  => $extra->task_code,
                                'old_status' => 'in_progress',
                                'new_status' => 'assigned'
                            ]);
                        }
                    }

                    \Modules\Board\Services\TaskLifecycleService::triggerTaskStartedWebhook((int)$primaryTask->id, 'in_progress', [
                        'resumed'          => true,
                        'watchdog_check'   => true,
                        'triggered_by'     => 'Watchdog'
                    ]);

                    $results[$prj->project_code] = [
                        'status' => 'ensured_running',
                        'active' => $primaryTask->task_code
                    ];
                } else {
                    $dispatched = \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable($pId);
                    if ($dispatched) {
                        $results[$prj->project_code] = [
                            'status'    => 'auto_dispatched',
                            'task_code' => $dispatched['task_code'] ?? null
                        ];
                    } else {
                        $results[$prj->project_code] = ['status' => 'idle'];
                    }
                }
            }
            return ['success' => true, 'projects' => $results];
        } catch (\Throwable $e) {
            error_log("WatchdogService runInspection error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
