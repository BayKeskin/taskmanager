<?php
namespace Modules\AgentRules\Controllers;

use Core\BaseController;
use Core\View;
use Modules\AgentRules\Models\AgentRulesModel;

class AgentRulesController extends BaseController
{
    private AgentRulesModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AgentRulesModel();
    }

    /**
     * Modül Ana Sayfası
     */
    public function index(): void
    {
        $globalRules = $this->model->getGlobalRules();
        $projects = $this->model->getAllProjectsWithRules();

        $projectStatuses = [];
        foreach ($projects as $prj) {
            $wsPath = trim((string)$prj->workspace_path);
            $hasDir = !empty($wsPath) && is_dir($wsPath);
            $agentsFile = $hasDir ? (rtrim($wsPath, '/\\') . '/AGENTS.md') : null;
            $hasFile = $agentsFile && file_exists($agentsFile);
            $lastSync = $hasFile ? date('Y-m-d H:i:s', filemtime($agentsFile)) : null;

            $projectStatuses[] = [
                'id'             => (int)$prj->id,
                'project_code'   => (string)$prj->project_code,
                'title'          => (string)$prj->title,
                'workspace_path' => $wsPath,
                'has_dir'        => $hasDir,
                'has_file'       => $hasFile,
                'last_sync'      => $lastSync,
                'agent_rules'    => (string)($prj->agent_rules ?? ''),
                'rules_length'   => mb_strlen((string)($prj->agent_rules ?? ''))
            ];
        }

        View::render('AgentRules::index', [
            'globalRules'     => $globalRules,
            'projectStatuses' => $projectStatuses
        ]);
    }

    /**
     * Genel Kuralları Kaydetme AJAX Uç Noktası
     */
    public function saveGlobal(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => __('AgentRules::unauthorized')]);
            exit;
        }

        $content = trim($_POST['global_rules'] ?? '');
        if (empty($content)) {
            echo json_encode(['success' => false, 'message' => __('AgentRules::save_error')]);
            exit;
        }

        $res = $this->model->saveGlobalRules($content);
        echo json_encode([
            'success' => $res,
            'message' => $res ? __('AgentRules::save_success') : __('AgentRules::save_error')
        ]);
        exit;
    }

    /**
     * Tekil Projenin AGENTS.md Dosyasını Senkronize Eder
     */
    public function syncSingle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            echo json_encode(['success' => false, 'message' => __('AgentRules::sync_single_error', ['%s' => '#0'])]);
            exit;
        }

        $result = $this->executeSyncForProject($projectId);
        echo json_encode($result);
        exit;
    }

    /**
     * Tüm Projelerin AGENTS.md Dosyalarını Toplu Senkronize Eder
     */
    public function syncAll(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $projects = $this->model->getAllProjectsWithRules();
        $globalRules = $this->model->getGlobalRules();

        $successCount = 0;
        $skipCount = 0;
        $details = [];

        foreach ($projects as $prj) {
            $res = $this->writeProjectAgentsFile($prj, $globalRules);
            if ($res['success']) {
                $successCount++;
            } else {
                $skipCount++;
            }
            $details[] = $res;
        }

        echo json_encode([
            'success'       => true,
            'message'       => sprintf(__('AgentRules::sync_all_success'), $successCount, $skipCount),
            'success_count' => $successCount,
            'skip_count'    => $skipCount,
            'details'       => $details
        ]);
        exit;
    }

    /**
     * Tekil proje için senkronizasyonu yürüten yardımcı metot
     */
    public function executeSyncForProject(int $projectId): array
    {
        $prj = $this->model->getProjectById($projectId);
        if (!$prj) {
            return [
                'success' => false,
                'message' => sprintf(__('AgentRules::sync_single_error'), (string)$projectId)
            ];
        }

        $globalRules = $this->model->getGlobalRules();
        return $this->writeProjectAgentsFile($prj, $globalRules);
    }

    /**
     * Projenin AGENTS.md dosyasını standart şablonla yazar
     */
    private function writeProjectAgentsFile(object $prj, string $globalRules): array
    {
        $wsPath = trim((string)$prj->workspace_path);
        $projectCode = (string)$prj->project_code;
        $title = (string)$prj->title;
        $rules = trim((string)($prj->agent_rules ?? ''));

        if (empty($wsPath) || !is_dir($wsPath)) {
            return [
                'success'      => false,
                'project_code' => $projectCode,
                'error'        => 'workspace_path_not_found',
                'message'      => sprintf(__('AgentRules::sync_single_error'), $projectCode)
            ];
        }

        $combinedContent = rtrim($globalRules) . "\n\n";
        $combinedContent .= "<!-- PROJECT_RULES_START -->\n";
        $combinedContent .= "## 6. PROJECT-SPECIFIC RULES: [{$projectCode}] {$title}\n\n";
        if (!empty($rules)) {
            $combinedContent .= $rules . "\n";
        } else {
            $combinedContent .= "- No project-specific rules defined yet.\n";
        }
        $combinedContent .= "<!-- PROJECT_RULES_END -->\n";

        $targetFile = rtrim($wsPath, '/\\') . '/AGENTS.md';

        try {
            $written = @file_put_contents($targetFile, $combinedContent);
            if ($written !== false) {
                return [
                    'success'      => true,
                    'project_code' => $projectCode,
                    'file'         => $targetFile,
                    'bytes'        => $written,
                    'last_sync'    => date('Y-m-d H:i:s'),
                    'message'      => sprintf(__('AgentRules::sync_single_success'), $projectCode)
                ];
            }
        } catch (\Throwable $e) {}

        return [
            'success'      => false,
            'project_code' => $projectCode,
            'error'        => 'write_failed',
            'message'      => sprintf(__('AgentRules::sync_single_error'), $projectCode)
        ];
    }
}
