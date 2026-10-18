<?php
namespace Modules\LiveDeploy\Controllers;

use Core\BaseController;
use ProjectAnalyzer;
use HostingManager;
use LiveConfigGenerator;
use DeltaSyncEngine;

class AjaxController extends BaseController
{
    private function respond(bool $success, string $message = '', array $data = []): void
    {
        $payload = array_merge(['success' => $success, 'message' => $message], $data);
        if (!isset($payload['data']) && !empty($data)) {
            $payload['data'] = $data;
        }
        $this->json($payload);
    }

    public function analyzeProject(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $proj = null;
        if ($projectId > 0) {
            $proj = $this->db->row_query("SELECT * FROM projects WHERE id = ?", [$projectId]);
        }
        $targetPath = trim($_POST['workspace_path'] ?? ($proj->workspace_path ?? ''));
        if (empty($targetPath)) {
            $this->respond(false, 'Projenin yerel çalışma alanı (workspace_path) tanımlı değil.');
        }

        $overrides = [
            'live_domain' => $_POST['live_domain'] ?? null,
            'db_host'     => $_POST['db_host'] ?? null,
            'db_name'     => $_POST['db_name'] ?? null,
            'db_user'     => $_POST['db_user'] ?? null,
            'db_pass'     => $_POST['db_pass'] ?? null,
        ];

        $analysis = ProjectAnalyzer::analyze($targetPath, array_filter($overrides));
        if (empty($analysis['success'])) {
            $this->respond(false, $analysis['error'] ?? 'Proje analiz edilemedi.', $analysis);
        }

        $this->respond(true, 'Proje ortam değişkenleri ve canlı yapılandırma analizi tamamlandı.', ['analysis' => $analysis]);
    }

    public function testHostingPanel(): void
    {
        $panelType = trim($_POST['panel_type'] ?? ($_POST['type'] ?? 'standard'));
        $config = $_POST['config'] ?? $_POST;
        $res = HostingManager::testCredentials($panelType, $config);
        if (!empty($res['success'])) {
            $this->respond(true, 'Barındırma paneli bağlantısı başarıyla doğrulandı.', ['result' => $res]);
        } else {
            $this->respond(false, 'Barındırma paneli bağlantısı başarısız.', ['result' => $res]);
        }
    }

    public function provisionHostingDb(): void
    {
        $panelType = trim($_POST['panel_type'] ?? ($_POST['type'] ?? 'standard'));
        $config    = $_POST['config'] ?? $_POST;
        $dbName    = trim($_POST['db_name'] ?? ($_POST['database'] ?? ''));
        $dbUser    = trim($_POST['db_user'] ?? ($_POST['username'] ?? ''));
        $dbPass    = trim($_POST['db_pass'] ?? ($_POST['password'] ?? ''));

        if (empty($dbName)) {
            $this->respond(false, 'Oluşturulacak veritabanı adı (db_name) zorunludur.');
        }

        $res = HostingManager::provision($panelType, $config, $dbName, $dbUser, $dbPass);
        if (!empty($res['success'])) {
            $this->respond(true, 'Canlı veritabanı ve kullanıcısı başarıyla tahsis edildi.', ['result' => $res]);
        } else {
            $this->respond(false, 'Veritabanı tahsisi başarısız oldu.', ['result' => $res]);
        }
    }

    public function generateLiveConfig(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $proj = null;
        if ($projectId > 0) {
            $proj = $this->db->row_query("SELECT * FROM projects WHERE id = ?", [$projectId]);
        }
        $targetPath = $proj->workspace_path ?? '';
        if (empty($targetPath)) {
            $this->respond(false, 'Projenin yerel çalışma alanı (workspace_path) tanımlı değil.');
        }

        $liveDomain = trim($_POST['live_domain'] ?? 'https://example.com');
        $dbConfig = [
            'host'     => $_POST['db_host'] ?? 'localhost',
            'database' => $_POST['db_name'] ?? '',
            'username' => $_POST['db_user'] ?? '',
            'password' => $_POST['db_pass'] ?? '',
            'port'     => (int)($_POST['db_port'] ?? 3306)
        ];

        $analysis = ProjectAnalyzer::analyze($targetPath, [
            'live_domain' => $liveDomain,
            'db_name'     => $dbConfig['database'],
            'db_user'     => $dbConfig['username'],
            'db_pass'     => $dbConfig['password']
        ]);

        $generatedFiles = [];
        $replacements = [];
        foreach (($analysis['live_payload']['replacements'] ?? []) as $k => $info) {
            $replacements[$k] = $info['target_live'];
        }

        if (file_exists("{$targetPath}/.env")) {
            $generatedFiles['.env'] = LiveConfigGenerator::generateDotEnv(@file_get_contents("{$targetPath}/.env") ?: '', $replacements);
        }
        if (file_exists("{$targetPath}/config/database.php")) {
            $generatedFiles['config/database.php'] = LiveConfigGenerator::generatePhpConfig(@file_get_contents("{$targetPath}/config/database.php") ?: '', $dbConfig);
        }

        $this->respond(true, 'Canlıya özel izolasyonlu konfigürasyon dosyaları başarıyla üretildi.', [
            'generated_files' => $generatedFiles,
            'file_count'      => count($generatedFiles),
            'local_intact'    => true
        ]);
    }

    public function dumpProjectSchema(): void
    {
        $liveDomain = trim($_POST['live_domain'] ?? 'https://example.com');
        $dumpData = ($_POST['dump_data'] ?? true) == true;

        $localPdo = $this->db->connect;
        $sqlDump = LiveConfigGenerator::dumpDatabase($localPdo, ['dump_data' => $dumpData]);
        $oldDomain = 'http://localhost/taskmanager';
        $transformedSql = LiveConfigGenerator::replaceDomainsInSql($sqlDump, $oldDomain, $liveDomain);

        $this->respond(true, 'Yerel veritabanı şeması ve verileri canlı domain eşitlemesiyle başarıyla dışa aktarıldı.', [
            'sql_size'    => strlen($transformedSql),
            'sql_preview' => substr($transformedSql, 0, 1000) . '...',
            'sql'         => $transformedSql
        ]);
    }

    public function deltaDiff(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $proj = null;
        if ($projectId > 0) {
            $proj = $this->db->row_query("SELECT * FROM projects WHERE id = ?", [$projectId]);
        }
        $targetPath = $proj->workspace_path ?? '';
        if (empty($targetPath)) {
            $this->respond(false, 'Projenin yerel çalışma alanı (workspace_path) tanımlı değil.');
        }

        $lastDep = DeltaSyncEngine::getLastDeployment($projectId);
        $fromCommit = $_POST['from_commit'] ?? ($lastDep->to_commit ?? null);
        $toCommit   = $_POST['to_commit'] ?? 'HEAD';

        $diff = DeltaSyncEngine::calculateGitDiff($targetPath, $fromCommit, $toCommit);
        if (empty($diff['success'])) {
            $this->respond(false, $diff['error'] ?? 'Git farkı hesaplanamadı.', $diff);
        }

        $pendingMigrations = DeltaSyncEngine::detectPendingMigrations($targetPath, $fromCommit, $toCommit);

        $this->respond(true, 'Akıllı delta senkronizasyon dosya farkları ve şema göçleri başarıyla hesaplandı.', [
            'diff'               => $diff,
            'pending_migrations' => $pendingMigrations,
            'migration_count'    => count($pendingMigrations)
        ]);
    }

    public function recordDeployment(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $proj = null;
        if ($projectId > 0) {
            $proj = $this->db->row_query("SELECT * FROM projects WHERE id = ?", [$projectId]);
        }
        if (!$proj) {
            $this->respond(false, 'Proje bulunamadı.');
        }

        $depType    = trim($_POST['deployment_type'] ?? 'delta');
        $fromCommit = $_POST['from_commit'] ?? null;
        $toCommit   = trim($_POST['to_commit'] ?? '');

        if (empty($toCommit)) {
            $toCommit = trim(shell_exec("cd /d \"{$proj->workspace_path}\" && git rev-parse HEAD") ?: 'unknown');
        }

        $filesUp  = (int)($_POST['files_uploaded'] ?? 0);
        $filesDel = (int)($_POST['files_deleted'] ?? 0);
        $migsRun  = (int)($_POST['migrations_run'] ?? 0);
        $details  = $_POST['details'] ?? [];

        $depId = DeltaSyncEngine::recordDeployment($projectId, [
            'deployment_type' => $depType,
            'from_commit'     => $fromCommit,
            'to_commit'       => $toCommit,
            'status'          => 'completed',
            'files_uploaded'  => $filesUp,
            'files_deleted'   => $filesDel,
            'migrations_run'  => $migsRun,
            'details'         => $details,
            'deployed_by'     => $this->user->id ?? 1
        ]);

        $this->respond(true, 'Proje dağıtımı ve delta senkronizasyon kaydı başarıyla tamamlandı.', [
            'deployment_id' => $depId,
            'project_code'  => $proj->project_code,
            'type'          => $depType,
            'to_commit'     => $toCommit,
            'status'        => 'completed'
        ]);
    }

    public function liveHealthcheck(): void
    {
        $liveUrl = trim($_POST['live_url'] ?? ($_GET['live_url'] ?? ''));
        if (empty($liveUrl)) {
            $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
            if ($projectId > 0) {
                $lastDep = DeltaSyncEngine::getLastDeployment($projectId);
                if ($lastDep && !empty($lastDep->details)) {
                    $det = is_string($lastDep->details) ? json_decode($lastDep->details, true) : (array)$lastDep->details;
                    $liveUrl = $det['live_domain'] ?? '';
                }
            }
        }

        if (empty($liveUrl)) {
            $this->respond(false, 'Test edilecek canlı site URL adresi belirtilmedi.');
        }

        $res = DeltaSyncEngine::performHealthcheck($liveUrl);
        if (!empty($res['success'])) {
            $this->respond(true, 'Canlı site sağlık kontrolü başarıyla doğrulandı.', ['result' => $res]);
        } else {
            $this->respond(false, $res['message'] ?? 'Canlı site sağlık kontrolü başarısız.', ['result' => $res]);
        }
    }

    public function rollbackDeployment(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $deploymentId = !empty($_POST['deployment_id']) ? (int)$_POST['deployment_id'] : null;

        if ($projectId <= 0) {
            $this->respond(false, 'Geçerli bir proje ID belirtilmelidir.');
        }

        $res = DeltaSyncEngine::rollbackDeployment($projectId, $deploymentId, $this->db->connect);
        if (!empty($res['success'])) {
            $this->respond(true, 'Canlı dağıtım cerrahi olarak önceki stabil sürüme başarıyla geri alındı.', ['result' => $res]);
        } else {
            $this->respond(false, $res['message'] ?? 'Geri alma işlemi başarısız oldu.', ['result' => $res]);
        }
    }
}
