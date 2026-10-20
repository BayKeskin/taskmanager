<?php
namespace Modules\Reports\Controllers;

use Core\BaseController;
use Modules\Reports\Models\ReportModel;

class ReportController extends BaseController
{
    private ReportModel $reportModel;

    public function __construct()
    {
        parent::__construct();
        $this->reportModel = new ReportModel();
    }

    /**
     * Raporlar ve analitik paneli için metrikleri döner
     */
    public function index(?int $activeProjectId = null, ?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $isClient = ($currentUser->role === 'client');
        if ($isClient) {
            $activeProjectId = (int)($currentUser->client_project_id ?? 0);
        }

        $metrics = $this->reportModel->getAnalyticsSummary($activeProjectId);
        $billingSummary = $this->reportModel->getProjectBillingSummary($activeProjectId);

        $projectModel = new \Modules\Projects\Models\ProjectModel();
        $allProjects = $projectModel->getProjects();

        return array_merge([
            'currentUser'     => $currentUser,
            'isClient'        => $isClient,
            'activeProjectId' => $activeProjectId,
            'allProjects'     => $allProjects,
            'billingSummary'  => $billingSummary
        ], $metrics);
    }

    /**
     * Hakediş / Teslimat Raporu verilerini döner
     */
    public function getBillingData(?int $projectId = null, ?float $hourlyRate = null): array
    {
        return $this->reportModel->getProjectBillingSummary($projectId, $hourlyRate);
    }
}
