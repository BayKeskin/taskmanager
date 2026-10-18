<?php
/**
 * Legacy McpController Facade
 * Delegates directly to HMVC Module: Modules\Mcp\Controllers\McpController
 */
require_once __DIR__ . '/Controller.php';

class McpController extends Controller
{
    private \Modules\Mcp\Controllers\McpController $moduleController;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->moduleController = new \Modules\Mcp\Controllers\McpController();
    }

    public function getProjectMcps(int $projectId): array
    {
        return $this->moduleController->getProjectMcps($projectId);
    }

    public function saveProjectMcp(int $projectId, int $mcpCatalogId, bool $isEnabled, array $envValues = [], array $customArgs = []): array
    {
        return $this->moduleController->saveProjectMcp($projectId, $mcpCatalogId, $isEnabled, $envValues, $customArgs);
    }
}
