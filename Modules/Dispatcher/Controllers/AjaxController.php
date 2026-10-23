<?php
namespace Modules\Dispatcher\Controllers;

use Core\BaseController;
use Modules\Dispatcher\Models\DispatcherModel;
use Modules\Dispatcher\Services\DispatcherService;
use Modules\Board\Services\TaskLifecycleService;

class AjaxController extends BaseController
{
    private DispatcherService $dispatcherService;
    private DispatcherModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->dispatcherService = new DispatcherService();
        $this->model = new DispatcherModel();
    }

    /**
     * AJAX: dispatcher_get_status
     * Returns real-time metrics, queue items, active tasks and recent logs
     */
    public function dispatcherGetStatus(): void
    {
        $running = $this->dispatcherService->isRunning();
        $queuedTasks = $this->model->getQueuedAiTasks();
        $activeTasks = $this->model->getActiveAiTasks();
        $projects = $this->model->getProjectsWithWorkspace();
        $recentLogs = $this->model->getRecentLogs(100);

        $this->json([
            'status'        => 'success',
            'is_running'    => $running,
            'queued_count'  => count($queuedTasks),
            'active_count'  => count($activeTasks),
            'project_count' => count($projects),
            'queued_tasks'  => $queuedTasks,
            'active_tasks'  => $activeTasks,
            'recent_logs'   => $recentLogs
        ]);
    }

    /**
     * AJAX: dispatcher_trigger_task
     * Manually triggers next queue processing or specific task dispatch
     */
    public function dispatcherTriggerTask(): void
    {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($taskId > 0) {
            $task = $this->model->getTaskById($taskId);
            if (!$task) {
                $this->json(['status' => 'error', 'message' => __('Dispatcher::msg_not_found')]);
                return;
            }

            $pId = $projectId ?: (int)$task['project_id'];
            $this->model->updateTaskStatus($taskId, 'in_progress');
            $dispatched = TaskLifecycleService::triggerTaskStartedWebhook($taskId, 'assigned');

            $this->json([
                'status'     => 'success',
                'message'    => __('Dispatcher::msg_dispatch_success'),
                'dispatched' => $dispatched
            ]);
            return;
        }

        if ($projectId > 0) {
            $result = TaskLifecycleService::dispatchNextAiTaskIfAvailable($projectId);
            $this->json([
                'status'  => 'success',
                'message' => __('Dispatcher::msg_dispatch_success'),
                'result'  => $result
            ]);
            return;
        }

        $this->json(['status' => 'error', 'message' => __('Dispatcher::msg_not_found')]);
    }

    /**
     * AJAX: dispatcher_abort_task
     * Aborts an active or queued task and reverts status to 'todo'
     */
    public function dispatcherAbortTask(): void
    {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            $this->json(['status' => 'error', 'message' => __('Dispatcher::msg_not_found')]);
            return;
        }

        $task = $this->model->getTaskById($taskId);
        if (!$task) {
            $this->json(['status' => 'error', 'message' => __('Dispatcher::msg_not_found')]);
            return;
        }

        $updated = $this->model->updateTaskStatus($taskId, 'todo');

        if ($updated) {
            $this->json([
                'status'  => 'success',
                'message' => __('Dispatcher::msg_abort_success')
            ]);
        } else {
            $this->json([
                'status'  => 'error',
                'message' => __('Dispatcher::msg_abort_failed')
            ]);
        }
    }

    /**
     * AJAX: dispatcher_sync_workspaces
     */
    public function dispatcherSyncWorkspaces(): void
    {
        $ok = $this->dispatcherService->syncWorkspaces();
        $this->json([
            'status'  => $ok ? 'success' : 'error',
            'message' => $ok
                ? __('Dispatcher::workspaces_synced')
                : __('Dispatcher::sync_error')
        ]);
    }
}
