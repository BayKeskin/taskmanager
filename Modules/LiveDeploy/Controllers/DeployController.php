<?php
namespace Modules\LiveDeploy\Controllers;

use Core\BaseController;
use Core\View;

/**
 * DeployController - Canlı hosting dağıtım sihirbazı ve operasyonlarını koordine eder.
 */
class DeployController extends BaseController
{
    /**
     * Dağıtım Sihirbazı Modal Görünümünü Render Eder
     */
    public function renderWizardModal(int $projectId): string
    {
        $this->requireAuth();
        return View::render('LiveDeploy::wizard_modal', ['projectId' => $projectId], true);
    }

    /**
     * Delta Diff Kontrolü (Hangi dosyalar ve göçler aktarılacak?)
     */
    public function getDeltaDiff(int $projectId): array
    {
        $this->requireAuth();

        require_once dirname(__DIR__, 3) . '/Model/ProjectModel.php';
        $project = (new \ProjectModel($this->db))->getProjectById($projectId);
        if (!$project) {
            return ['success' => false, 'error' => 'Proje bulunamadı.'];
        }

        require_once dirname(__DIR__) . '/Services/DeltaSyncEngine.php';
        $syncEngine = new \DeltaSyncEngine($this->db);
        return $syncEngine->getDeltaDiff($project);
    }

    /**
     * Canlı Sağlık Kontrolü (Healthcheck)
     */
    public function checkHealth(string $url): array
    {
        $this->requireAuth();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $start = microtime(true);
        $response = curl_exec($ch);
        $time = round((microtime(true) - $start) * 1000, 2);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isHealthy = ($httpCode >= 200 && $httpCode < 400);

        return [
            'success'      => true,
            'http_code'    => $httpCode,
            'healthy'      => $isHealthy,
            'latency_ms'   => $time,
            'url'          => $url
        ];
    }
}
