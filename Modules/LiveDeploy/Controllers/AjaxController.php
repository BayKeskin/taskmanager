<?php
namespace Modules\LiveDeploy\Controllers;

use Core\BaseController;
use ProjectAnalyzer;
use HostingManager;
use LiveConfigGenerator;
use DeltaSyncEngine;
use Modules\LiveDeploy\Models\DeployModel;

class AjaxController extends BaseController
{
    private DeployModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new DeployModel();
    }

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
            $proj = $this->model->getProjectById($projectId);
        }
        $targetPath = trim($_POST['workspace_path'] ?? ($proj->workspace_path ?? ''));
        if (empty($targetPath)) {
            $this->respond(false, __('LiveDeploy::err_workspace_not_found'));
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
            $this->respond(false, $analysis['error'] ?? __('LiveDeploy::err_analysis_failed'), $analysis);
        }

        $this->respond(true, __('LiveDeploy::msg_analysis_complete'), ['analysis' => $analysis]);
    }

    public function testHostingPanel(): void
    {
        $panelType = trim($_POST['panel_type'] ?? ($_POST['type'] ?? 'standard'));
        $config = $_POST['config'] ?? $_POST;
        $res = HostingManager::testCredentials($panelType, $config);
        if (!empty($res['success'])) {
            $this->respond(true, __('LiveDeploy::hosting_test_success'), ['result' => $res]);
        } else {
            $this->respond(false, __('LiveDeploy::err_panel_conn_failed'), ['result' => $res]);
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
            $this->respond(false, __('LiveDeploy::err_db_name_required'));
        }

        $res = HostingManager::provision($panelType, $config, $dbName, $dbUser, $dbPass);
        if (!empty($res['success'])) {
            $this->respond(true, __('LiveDeploy::msg_db_provisioned'), ['result' => $res]);
        } else {
            $this->respond(false, __('LiveDeploy::err_db_provision_failed'), ['result' => $res]);
        }
    }

    public function generateLiveConfig(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $proj = null;
        if ($projectId > 0) {
            $proj = $this->model->getProjectById($projectId);
        }
        $targetPath = $proj->workspace_path ?? '';
        if (empty($targetPath)) {
            $this->respond(false, __('LiveDeploy::err_workspace_not_found'));
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

        $this->respond(true, __('LiveDeploy::deploy_config_generated'), [
            'generated_files' => $generatedFiles,
            'file_count'      => count($generatedFiles),
            'local_intact'    => true
        ]);
    }

    public function dumpProjectSchema(): void
    {
        $liveDomain = trim($_POST['live_domain'] ?? 'https://example.com');
        $dumpData = ($_POST['dump_data'] ?? true) == true;

        $sqlDump = LiveConfigGenerator::dumpDatabaseWithModel($this->model, ['dump_data' => $dumpData]);
        $oldDomain = function_exists('siteUrl') ? siteUrl() : 'http://localhost';
        $transformedSql = LiveConfigGenerator::replaceDomainsInSql($sqlDump, $oldDomain, $liveDomain);

        $this->respond(true, __('LiveDeploy::deploy_schema_dumped'), [
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
            $proj = $this->model->getProjectById($projectId);
        }
        $targetPath = $proj->workspace_path ?? '';
        if (empty($targetPath)) {
            $this->respond(false, __('LiveDeploy::err_workspace_not_found'));
        }

        $lastDep = DeltaSyncEngine::getLastDeployment($projectId);
        $fromCommit = $_POST['from_commit'] ?? ($lastDep->to_commit ?? null);
        $toCommit   = $_POST['to_commit'] ?? 'HEAD';

        $diff = DeltaSyncEngine::calculateGitDiff($targetPath, $fromCommit, $toCommit);
        if (empty($diff['success'])) {
            $this->respond(false, $diff['error'] ?? __('LiveDeploy::err_git_diff_failed'), $diff);
        }

        $pendingMigrations = DeltaSyncEngine::detectPendingMigrations($targetPath, $fromCommit, $toCommit);

        $this->respond(true, __('LiveDeploy::deploy_delta_diff_calculated'), [
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
            $proj = $this->model->getProjectById($projectId);
        }
        if (!$proj) {
            $this->respond(false, __('LiveDeploy::err_project_not_found'));
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

        $this->respond(true, __('LiveDeploy::deploy_deployment_recorded'), [
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
            $this->respond(false, __('LiveDeploy::err_live_url_missing'));
        }

        $res = DeltaSyncEngine::performHealthcheck($liveUrl);
        if (!empty($res['success'])) {
            $this->respond(true, __('LiveDeploy::deploy_success'), ['result' => $res]);
        } else {
            $this->respond(false, $res['message'] ?? __('LiveDeploy::err_healthcheck_failed'), ['result' => $res]);
        }
    }

    public function rollbackDeployment(): void
    {
        $projectId = (int)($_POST['project_id'] ?? ($_GET['project_id'] ?? 0));
        $deploymentId = !empty($_POST['deployment_id']) ? (int)$_POST['deployment_id'] : null;

        if ($projectId <= 0) {
            $this->respond(false, __('LiveDeploy::err_invalid_project_id'));
        }

        $res = DeltaSyncEngine::rollbackDeployment($projectId, $deploymentId, null);
        if (!empty($res['success'])) {
            $this->respond(true, __('LiveDeploy::msg_rollback_success'), ['result' => $res]);
        } else {
            $this->respond(false, $res['message'] ?? __('LiveDeploy::err_rollback_failed'), ['result' => $res]);
        }
    }
}
