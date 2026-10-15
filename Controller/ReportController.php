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

        return array_merge(['currentUser' => $currentUser], $metrics);
    }
}
