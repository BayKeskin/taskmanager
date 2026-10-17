<?php

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../Model/ReportModel.php';

class ReportController extends Controller
{
    private ReportModel $reportModel;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->reportModel = new ReportModel($this->db);
    }

    /**
     * Raporlar ve analitik paneli için metrikleri döner
     */
    public function index(?object $currentUser = null): array
    {
        $currentUser = $currentUser ?? ($_SESSION['user'] ?? (object)['id' => 1, 'role' => 'admin']);
        $metrics = $this->reportModel->getAnalyticsSummary();

        // Müşteri ise yalnızca yetkili olduğu projenin hakedişini getir
        $isClient = ($currentUser->role === 'client');
        $activeProjectId = $isClient ? (int)($currentUser->client_project_id ?? 0) : null;
        $billingSummary = $this->reportModel->getProjectBillingSummary($activeProjectId);

        return array_merge([
            'currentUser'    => $currentUser,
            'isClient'       => $isClient,
            'billingSummary' => $billingSummary
        ], $metrics);
    }

    /**
     * Hakediş / Teslimat Raporu verilerini döner
     */
    public function getBillingData(?int $projectId = null, float $hourlyRate = 500.0): array
    {
        return $this->reportModel->getProjectBillingSummary($projectId, $hourlyRate);
    }
}
