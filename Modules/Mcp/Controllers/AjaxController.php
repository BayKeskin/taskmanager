<?php
namespace Modules\Mcp\Controllers;

use Core\BaseController;
use Modules\Mcp\Controllers\McpController;

class AjaxController extends BaseController
{
    private McpController $mcpCtrl;

    public function __construct()
    {
        parent::__construct();
        $this->mcpCtrl = new McpController();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function getProjectMcps(): void
    {
        $pId = (int)($_GET['project_id'] ?? ($_POST['project_id'] ?? 0));
        $res = $this->mcpCtrl->getProjectMcps($pId);
        if (!empty($res['success'])) {
            $this->respond(true, 'MCP listesi getirildi.', $res);
        } else {
            $this->respond(false, $res['error'] ?? 'Hata oluştu.');
        }
    }

    public function saveProjectMcp(): void
    {
        $pId = (int)($_POST['project_id'] ?? 0);
        $catalogId = (int)($_POST['mcp_catalog_id'] ?? 0);
        $isEnabled = !empty($_POST['is_enabled']);
        $envValues = is_array($_POST['env_values'] ?? null) ? $_POST['env_values'] : (json_decode($_POST['env_values'] ?? '{}', true) ?: []);
        $customArgs = is_array($_POST['custom_args'] ?? null) ? $_POST['custom_args'] : (json_decode($_POST['custom_args'] ?? '[]', true) ?: []);

        $res = $this->mcpCtrl->saveProjectMcp($pId, $catalogId, $isEnabled, $envValues, $customArgs);
        if (!empty($res['success'])) {
            $this->respond(true, $res['message'] ?? 'MCP kaydedildi.', $res);
        } else {
            $this->respond(false, $res['error'] ?? 'Kaydedilemedi.');
        }
    }

    public function toggleProjectMcp(): void
    {
        $pId = (int)($_POST['project_id'] ?? 0);
        $catalogId = (int)($_POST['mcp_catalog_id'] ?? 0);
        $isEnabled = !empty($_POST['is_enabled']);

        $res = $this->mcpCtrl->toggleProjectMcp($pId, $catalogId, $isEnabled);
        if (!empty($res['success'])) {
            $this->respond(true, 'MCP durumu güncellendi.', $res);
        } else {
            $this->respond(false, $res['error'] ?? 'Güncellenemedi.');
        }
    }

    public function syncProjectMcps(): void
    {
        $pId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $res = $this->mcpCtrl->syncFiles($pId);
        if (!empty($res['success'])) {
            $this->respond(true, 'MCP dosyaları başarıyla senkronize edildi.', $res);
        } else {
            $this->respond(false, $res['result']['error'] ?? 'Senkronizasyon başarısız.');
        }
    }
}
