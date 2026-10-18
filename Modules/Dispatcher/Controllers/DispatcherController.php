<?php
namespace Modules\Dispatcher\Controllers;

use Core\BaseController;
use Modules\Dispatcher\Services\DispatcherService;

class DispatcherController extends BaseController
{
    private DispatcherService $dispatcherService;

    public function __construct()
    {
        parent::__construct();
        $this->dispatcherService = new DispatcherService();
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
                ? __('Dispatcher::dispatcher_running', 'Dispatcher Aktif (Port 4000)')
                : __('Dispatcher::dispatcher_stopped', 'Dispatcher Durduruldu')
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
                ? __('Dispatcher::workspaces_synced', 'Çalışma alanları senkronize edildi.')
                : 'Senkronizasyon hatası.'
        ]);
    }
}
