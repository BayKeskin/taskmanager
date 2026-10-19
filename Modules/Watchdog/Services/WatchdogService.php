<?php
namespace Modules\Watchdog\Services;

use Modules\Watchdog\Models\WatchdogModel;

/**
 * WatchdogService - Otonom Kurtarma & Kuyruk Denetim Servisi
 * Tıkanan veya birden fazla 'in_progress' kalan AI görevlerini denetler ve kuyruğu canlı tutar.
 */
class WatchdogService
{
    private static ?WatchdogModel $model = null;

    private static function getModel(): WatchdogModel
    {
        if (self::$model === null) {
            self::$model = new WatchdogModel();
        }
        return self::$model;
    }

    public static function runInspection(): array
    {
        $restartSignal = dirname(__DIR__, 3) . '/Modules/Dispatcher/Bin/restart.signal';
        if (file_exists($restartSignal)) {
            @unlink($restartSignal);
            @file_put_contents(dirname(__DIR__, 3) . '/Modules/Dispatcher/Bin/restarted.log', date('Y-m-d H:i:s') . " - Dispatcher restarting by watchdog signal...\n", FILE_APPEND);
            @pclose(@popen('start "" /b cmd /c "taskkill /F /IM pythonw.exe & timeout /t 1 & cscript //nologo c:\wamp64\www\taskmanager\dispatcher\start_silent.vbs"', 'r'));
        }

        $results = [];

        try {
            $watchdogModel = self::getModel();
            $projects = $watchdogModel->getActiveProjects();

            foreach ($projects as $prj) {
                $p = (object)$prj;
                $pId = (int)$p->id;
                
                $allInProgress = $watchdogModel->getInProgressTasks($pId);
                $runningAiTasks = array_values(array_filter($allInProgress, fn($t) => \Modules\Board\Services\TaskLifecycleService::isAiTask((object)$t)));

                if (count($runningAiTasks) >= 1) {
                    $primaryTask = (object)$runningAiTasks[0];
                    if (count($runningAiTasks) > 1) {
                        for ($i = 1; $i < count($runningAiTasks); $i++) {
                            $extra = (object)$runningAiTasks[$i];
                            $watchdogModel->resetTaskToAssigned((int)$extra->id);
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

                    $results[$p->project_code] = [
                        'status' => 'ensured_running',
                        'active' => $primaryTask->task_code
                    ];
                } else {
                    $dispatched = \Modules\Board\Services\TaskLifecycleService::dispatchNextAiTaskIfAvailable($pId);
                    if ($dispatched) {
                        $results[$p->project_code] = [
                            'status'    => 'auto_dispatched',
                            'task_code' => $dispatched['task_code'] ?? null
                        ];
                    } else {
                        $results[$p->project_code] = ['status' => 'idle'];
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
