<?php
/**
 * Legacy ReportController Facade
 * Delegates directly to HMVC Module: Modules\Reports\Controllers\ReportController
 */
require_once __DIR__ . '/Controller.php';

class ReportController extends Controller
{
    private \Modules\Reports\Controllers\ReportController $moduleController;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->moduleController = new \Modules\Reports\Controllers\ReportController();
    }

    public function index(?object $currentUser = null): array
    {
        return $this->moduleController->index($currentUser);
    }

    public function getBillingData(?int $projectId = null, ?float $hourlyRate = null): array
    {
        return $this->moduleController->getBillingData($projectId, $hourlyRate);
    }
}
