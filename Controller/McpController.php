<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/McpModel.php';
require_once __DIR__ . '/../Model/ProjectModel.php';

class McpController extends Controller
{
    private McpModel $mcpModel;
    private ProjectModel $projectModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->mcpModel = new McpModel($this->db);
        $this->projectModel = new ProjectModel($this->db);
    }

    /**
     * Projeye ait tüm MCP'leri katalog bilgileriyle listeler
     */
    public function getProjectMcps(int $projectId): array
    {
        $project = $this->projectModel->getProjectById($projectId);
        if (!$project) {
            return ['success' => false, 'error' => 'Proje bulunamadı.'];
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
            return ['success' => false, 'error' => 'MCP ayarları kaydedilemedi.'];
        }

        // Yerel workspace dosyasını (.agents/plugins) anında senkronize et
        $syncResult = $this->mcpModel->syncProjectMcpFiles($projectId);

        return [
            'success' => true,
            'message' => 'MCP ayarları başarıyla kaydedildi.',
            'sync' => $syncResult
        ];
    }

    /**
     * MCP hızlı Aç/Kapat Toggle
     */
    public function toggleProjectMcp(int $projectId, int $mcpCatalogId, bool $isEnabled): array
    {
        $ok = $this->mcpModel->toggleProjectMcp($projectId, $mcpCatalogId, $isEnabled);
        if (!$ok) {
            return ['success' => false, 'error' => 'Durum güncellenemedi.'];
        }

        $syncResult = $this->mcpModel->syncProjectMcpFiles($projectId);
        return [
            'success' => true,
            'is_enabled' => $isEnabled,
            'sync' => $syncResult
        ];
    }

    /**
     * Proje MCP dosyalarını yeniden senkronize et
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
