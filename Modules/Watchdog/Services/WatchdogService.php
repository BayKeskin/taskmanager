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
        $binDir = dirname(__DIR__, 2) . '/Dispatcher/Bin';
        $restartSignal = $binDir . '/restart.signal';
        if (file_exists($restartSignal)) {
            @unlink($restartSignal);
            @file_put_contents($binDir . '/restarted.log', date('Y-m-d H:i:s') . " - Dispatcher restarting by watchdog signal...\n", FILE_APPEND);
            $vbsPath = str_replace('/', '\\', $binDir . '/start_silent.vbs');
            @pclose(@popen('start "" /b cmd /c "taskkill /F /IM pythonw.exe & timeout /t 1 & cscript //nologo \"' . $vbsPath . '\""', 'r'));
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

                    // Görevin son güncellenme zamanını kontrol et (20 dakikadır hiç kıpırdamamışsa kurtar)
                    $updatedAt = !empty($primaryTask->updated_at) ? strtotime($primaryTask->updated_at) : 0;
                    $staleSeconds = time() - $updatedAt;

                    if ($updatedAt > 0 && $staleSeconds >= 1200) { // 20 dakika (1200 sn)
                        \Modules\Board\Services\TaskLifecycleService::triggerTaskStartedWebhook((int)$primaryTask->id, 'in_progress', [
                            'resumed'        => true,
                            'watchdog_check' => true,
                            'triggered_by'   => 'Watchdog-StaleRecovery'
                        ]);
                        $results[$p->project_code] = [
                            'status' => 'stale_resumed_after_20m',
                            'active' => $primaryTask->task_code
                        ];
                    } else {
                        // Ajan zaten aktif çalışıyor, asla araya girip interrupt atma!
                        $results[$p->project_code] = [
                            'status' => 'actively_working',
                            'active' => $primaryTask->task_code
                        ];
                    }
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

    /**
     * Sistem sağlık durumunu denetler ve tıkanmış (stale) görevleri kurtarır
     */
    public static function getHealthReport(int $minutesThreshold = 25): array
    {
        $watchdogModel = self::getModel();
        $staleTasks = $watchdogModel->getStaleInProgressTasks($minutesThreshold);
        $recoveredCount = 0;
        $recoveredTasks = [];

        foreach ($staleTasks as $stale) {
            $s = (object)$stale;
            $taskId = (int)$s->id;
            $taskCode = $s->task_code ?? "#{$taskId}";
            $projectId = (int)$s->project_id;

            if ($watchdogModel->resetStaleTaskToAssigned($taskId, "Stale in_progress for > {$minutesThreshold}m")) {
                $recoveredCount++;
                $recoveredTasks[] = [
                    'task_id'   => $taskId,
                    'task_code' => $taskCode,
                    'project'   => $s->project_code ?? ''
                ];

                // Canlı UI güncellemesi için SSE yayınla
                if (class_exists('\Modules\SSE\Services\SseBroadcaster')) {
                    \Modules\SSE\Services\SseBroadcaster::broadcast('task_status_changed', $projectId, $taskId, 1, [
                        'task_id'    => $taskId,
                        'task_code'  => $taskCode,
                        'old_status' => 'in_progress',
                        'new_status' => 'assigned',
                        'note'       => 'Watchdog stale task recovery'
                    ]);
                }
            }
        }

        // Genel kuyruk ve sistem denetimi
        $dispatcherRunning = false;
        if (class_exists('\Modules\Dispatcher\Services\DispatcherService')) {
            $dispatcherService = new \Modules\Dispatcher\Services\DispatcherService();
            $dispatcherRunning = $dispatcherService->isRunning();
        }

        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
        $memoryPeak  = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

        return [
            'status'                 => 'healthy',
            'watchdog_active'        => true,
            'dispatcher_running'     => $dispatcherRunning,
            'stale_tasks_recovered'  => $recoveredCount,
            'recovered_details'      => $recoveredTasks,
            'threshold_minutes'      => $minutesThreshold,
            'system_memory'          => [
                'usage' => $memoryUsage,
                'peak'  => $memoryPeak
            ],
            'timestamp'              => date('c')
        ];
    }
}

