<?php
namespace Modules\Projects\Controllers;

use Core\BaseController;
use Modules\Projects\Models\ProjectModel;

class AjaxController extends BaseController
{
    private ProjectModel $projectModel;

    public function __construct()
    {
        parent::__construct();
        $this->projectModel = new ProjectModel();
    }

    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function createProject(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim: Sadece yöneticiler proje oluşturabilir.');
        }

        $title = trim($_POST['title'] ?? '');
        $projectCode = strtoupper(trim($_POST['project_code'] ?? ''));
        $color = $_POST['color'] ?? '#7B68EE';
        $hourlyRate = isset($_POST['hourly_rate']) && is_numeric($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : 500.00;
        $description = trim($_POST['description'] ?? '');
        $workspacePath = trim($_POST['workspace_path'] ?? '') ?: null;
        $agentRules = trim($_POST['agent_rules'] ?? '') ?: null;
        $webhookUrl = trim($_POST['webhook_url'] ?? '') ?: null;
        $conversationId = trim($_POST['agent_conversation_id'] ?? '') ?: null;

        if (empty($title) || empty($projectCode)) {
            $this->respond(false, 'Proje adı ve proje kodu zorunludur.');
        }

        $newId = $this->projectModel->createProject([
            'project_code'          => $projectCode,
            'title'                 => $title,
            'description'           => $description,
            'color'                 => $color,
            'hourly_rate'           => $hourlyRate,
            'workspace_path'        => $workspacePath,
            'agent_rules'           => $agentRules,
            'webhook_url'           => $webhookUrl,
            'agent_conversation_id' => $conversationId,
            'created_by'            => $this->user->id
        ]);

        if ($newId > 0) {
            if (class_exists('Modules\Dispatcher\Services\DispatcherService')) {
                (new \Modules\Dispatcher\Services\DispatcherService())->syncWorkspaces();
            }
            if ($workspacePath && $agentRules) {
                \Modules\Projects\Services\ProjectService::syncProjectRulesToWorkspace($newId);
            }
            $this->respond(true, 'Proje başarıyla oluşturuldu!', ['project_id' => $newId]);
        } else {
            $this->respond(false, 'Proje eklenirken hata oluştu.');
        }
    }

    public function getProject(): void
    {
        $projectId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, 'Geçersiz proje ID.');
        }

        $proj = $this->projectModel->getProjectById($projectId);
        if (!$proj) {
            $this->respond(false, 'Proje bulunamadı.');
        }

        if (empty($proj->agent_rules) && !empty($proj->workspace_path)) {
            $ws = rtrim($proj->workspace_path, '/\\');
            $agentsMd = $ws . DIRECTORY_SEPARATOR . 'AGENTS.md';
            if (file_exists($agentsMd)) {
                $content = @file_get_contents($agentsMd);
                if ($content && preg_match('/<!-- PROJECT_RULES_START -->(.*?)<!-- PROJECT_RULES_END -->/s', $content, $m)) {
                    $extracted = trim($m[1]);
                    if ($extracted) {
                        $proj->agent_rules = $extracted;
                        $this->projectModel->updateProject($projectId, ['agent_rules' => $extracted]);
                    }
                }
            }
        }

        $this->respond(true, 'Proje bilgisi alındı', (array)$proj);
    }

    public function updateProject(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim: Sadece yöneticiler proje güncelleyebilir.');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $color = $_POST['color'] ?? '#7B68EE';
        $hourlyRate = isset($_POST['hourly_rate']) && is_numeric($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : 500.00;
        $description = trim($_POST['description'] ?? '');
        $workspacePath = trim($_POST['workspace_path'] ?? '') ?: null;
        $agentRules = trim($_POST['agent_rules'] ?? '') ?: null;
        $webhookUrl = trim($_POST['webhook_url'] ?? '') ?: null;
        $conversationId = trim($_POST['agent_conversation_id'] ?? '') ?: null;

        if ($projectId <= 0 || empty($title)) {
            $this->respond(false, 'Geçersiz proje bilgisi.');
        }

        $updated = $this->projectModel->updateProject($projectId, [
            'title'                 => $title,
            'description'           => $description,
            'color'                 => $color,
            'hourly_rate'           => $hourlyRate,
            'workspace_path'        => $workspacePath,
            'agent_rules'           => $agentRules,
            'webhook_url'           => $webhookUrl,
            'agent_conversation_id' => $conversationId
        ]);

        if ($updated) {
            if (class_exists('Modules\Dispatcher\Services\DispatcherService')) {
                (new \Modules\Dispatcher\Services\DispatcherService())->syncWorkspaces();
            }
            if ($workspacePath && $agentRules) {
                \Modules\Projects\Services\ProjectService::syncProjectRulesToWorkspace($projectId);
            }
            $this->respond(true, 'Proje ayarları ve AI webhook bilgisi güncellendi!');
        } else {
            $this->respond(false, 'Güncelleme sırasında hata oluştu.');
        }
    }

    public function syncProjectRules(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim: Sadece yöneticiler kural senkronizasyonu yapabilir.');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, 'Geçersiz proje ID.');
        }

        if (isset($_POST['agent_rules'])) {
            $agentRules = trim($_POST['agent_rules']) ?: null;
            $this->projectModel->updateProject($projectId, ['agent_rules' => $agentRules]);
        }

        $syncRes = \Modules\Projects\Services\ProjectService::syncProjectRulesToWorkspace($projectId);
        if (!empty($syncRes['success'])) {
            $this->respond(true, 'Proje kural dosyaları (AGENTS.md) başarıyla güncellendi!', $syncRes);
        } else {
            $this->respond(false, $syncRes['error'] ?? 'Kural dosyaları güncellenirken hata oluştu.');
        }
    }

    public function refineProjectRules(): void
    {
        if ($this->user->role !== 'admin' && $this->user->role !== 'employee') {
            $this->respond(false, 'Yetkisiz erişim.');
        }

        $rawRules = trim($_POST['raw_rules'] ?? '');
        $projectCode = trim($_POST['project_code'] ?? '');
        $projectTitle = trim($_POST['project_title'] ?? '');
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($projectId > 0 && (empty($projectCode) || empty($projectTitle))) {
            $proj = $this->projectModel->getProjectById($projectId);
            if ($proj) {
                $projectCode = $projectCode ?: ($proj->project_code ?? '');
                $projectTitle = $projectTitle ?: ($proj->title ?? '');
            }
        }

        $res = (new \Modules\AiArchitect\Services\GeminiClient())->refineRulesWithAi($rawRules, $projectCode, $projectTitle);
        if (!empty($res['success'])) {
            $this->respond(true, 'Kurallar yapay zeka ile başarıyla derlendi ve yapılandırıldı!', $res);
        } else {
            $this->respond(false, $res['error'] ?? 'Kurallar derlenirken bir hata oluştu.');
        }
    }

    public function testProjectWebhook(): void
    {
        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, 'Geçersiz proje ID.');
        }

        $res = \Modules\RestApi\Services\WebhookService::triggerProjectWebhook($projectId, 'project.webhook_test', [
            'message'    => 'Yapay Zeka (AI Agent) test pini başarıyla gönderildi.',
            'test_time'  => date('Y-m-d H:i:s'),
            'user'       => $this->user->name
        ]);

        if ($res['sent']) {
            $this->respond(true, '✅ Webhook başarıyla iletildi (HTTP ' . $res['http_code'] . ')', $res);
        } else {
            $this->respond(false, '❌ Webhook ulaşılamadı: ' . ($res['error'] ?? $res['reason'] ?? 'Bilinmeyen hata (HTTP ' . $res['http_code'] . ')'), $res);
        }
    }

    public function toggleProjectStatus(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim.');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'completed';

        if ($projectId <= 0) {
            $this->respond(false, 'Geçersiz proje ID.');
        }

        $this->db->exec_query("UPDATE projects SET status = ? WHERE id = ?", [$newStatus, $projectId]);
        
        $msg = ($newStatus === 'completed' || $newStatus === 'archived') 
            ? 'Proje geçmiş projelere taşındı.' 
            : 'Proje aktif projelere geri yüklendi.';
        $this->respond(true, $msg);
    }

    public function deleteProject(): void
    {
        if ($this->user->role !== 'admin') {
            $this->respond(false, 'Yetkisiz erişim.');
        }

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->respond(false, 'Geçersiz proje ID.');
        }

        $success = \Modules\Projects\Services\ProjectService::purgeProject($projectId);
        if ($success) {
            $this->respond(true, 'Proje, ilişkili tüm görevler, dökümanlar ve workspaces yapılandırması kalıcı olarak silindi.');
        }
        $this->respond(false, 'Proje bulunamadı veya silinemedi.');
    }
}
