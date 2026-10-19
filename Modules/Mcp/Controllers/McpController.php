<?php
namespace Modules\Mcp\Controllers;

use Core\BaseController;
use Modules\Mcp\Models\McpModel;
use ProjectModel;

class McpController extends BaseController
{
    private McpModel $mcpModel;
    private ?\Modules\Projects\Models\ProjectModel $projectModel = null;

    public function __construct()
    {
        parent::__construct();
        $this->mcpModel = new McpModel();
        if (class_exists('Modules\Projects\Models\ProjectModel')) {
            $this->projectModel = new \Modules\Projects\Models\ProjectModel();
        }
    }

    /**
     * Projeye ait tüm MCP'leri katalog bilgileriyle listeler
     */
    public function getProjectMcps(int $projectId): array
    {
        $project = $this->projectModel ? $this->projectModel->getProjectById($projectId) : null;
        if (!$project) {
            return ['success' => false, 'error' => __('Mcp::project_not_found')];
        }

        $mcps = $this->mcpModel->getProjectMcps($projectId);
        return [
            'success' => true,
            'project' => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'title' => $project->title,
                'workspace_path' => $project->workspace_path
            ],
            'mcps' => $mcps
        ];
    }

    /**
     * MCP ayarlarını kaydeder ve projeyle senkronize eder
     */
    public function saveProjectMcp(int $projectId, int $mcpCatalogId, bool $isEnabled, array $envValues = [], array $customArgs = []): array
    {
        $ok = $this->mcpModel->saveProjectMcp($projectId, $mcpCatalogId, $isEnabled, $envValues, $customArgs);
        if (!$ok) {
            return ['success' => false, 'error' => __('Mcp::save_fail')];
        }

        $syncResult = $this->mcpModel->syncProjectMcpFiles($projectId);

        return [
            'success' => true,
            'message' => __('Mcp::settings_saved'),
            'sync' => $syncResult
        ];
    }

    /**
     * MCP durumunu açar veya kapatır ve dosyalara senkronize eder
     */
    public function toggleProjectMcp(int $projectId, int $mcpCatalogId, bool $isEnabled): array
    {
        $existing = null;
        $mcps = $this->mcpModel->getProjectMcps($projectId);
        foreach ($mcps as $m) {
            if ((int)$m->id === $mcpCatalogId) {
                $existing = $m;
                break;
            }
        }

        $envValues = $existing ? ($existing->env_values ?? []) : [];
        $customArgs = $existing ? ($existing->custom_args ?? []) : [];

        $ok = $this->mcpModel->saveProjectMcp($projectId, $mcpCatalogId, $isEnabled, (array)$envValues, (array)$customArgs);
        if (!$ok) {
            return ['success' => false, 'error' => __('Mcp::update_failed')];
        }

        $syncResult = $this->mcpModel->syncProjectMcpFiles($projectId);

        return [
            'success' => true,
            'message' => __('Mcp::status_updated'),
            'sync' => $syncResult
        ];
    }

    /**
     * Projenin MCP dosyalarını yeniden senkronize eder
     */
    public function syncFiles(int $projectId): array
    {
        $syncResult = $this->mcpModel->syncProjectMcpFiles($projectId);
        return [
            'success' => !empty($syncResult['synced']),
            'result' => $syncResult
        ];
    }
}

