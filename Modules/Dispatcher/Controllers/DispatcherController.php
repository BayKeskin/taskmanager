<?php
namespace Modules\Dispatcher\Controllers;

use Core\BaseController;
use Core\View;
use Modules\Dispatcher\Models\DispatcherModel;
use Modules\Dispatcher\Services\DispatcherService;

class DispatcherController extends BaseController
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
     * Dispatcher Dashboard Main View
     */
    public function index(): void
    {
        $isRunning = $this->dispatcherService->isRunning();
        $queuedTasks = $this->model->getQueuedAiTasks();
        $activeTasks = $this->model->getActiveAiTasks();
        $projects = $this->model->getProjectsWithWorkspace();
        $recentLogs = $this->model->getRecentLogs(100);

        View::render('Dispatcher::index', [
            'isRunning'   => $isRunning,
            'queuedTasks' => $queuedTasks,
            'activeTasks' => $activeTasks,
            'projects'    => $projects,
            'recentLogs'  => $recentLogs
        ]);
    }

    /**
     * AJAX endpoint: check_dispatcher_status
     */
    public function checkStatus(): void
    {
        $running = $this->dispatcherService->isRunning();
        $this->json([
            'status'  => 'success',
            'running' => $running,
            'message' => $running
                ? __('Dispatcher::dispatcher_running')
                : __('Dispatcher::dispatcher_stopped')
        ]);
    }

    /**
     * AJAX endpoint: sync_workspaces
     */
    public function syncWorkspaces(): void
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
