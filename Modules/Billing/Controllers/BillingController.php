<?php
namespace Modules\Billing\Controllers;

use Core\BaseController;
use Core\View;
use Modules\Billing\Models\BillingModel;

/**
 * BillingController - Hakediş & İş Teslim Raporu Yöneticisi
 */
class BillingController extends BaseController
{
    private BillingModel $billingModel;

    public function __construct()
    {
        parent::__construct();
        $this->billingModel = new BillingModel();
    }

    /**
     * Hakediş verisini döndürür
     */
    public function getBillingData(?int $projectId = null, ?float $hourlyRate = null): array
    {
        $this->requireAuth();

        if ($this->user && $this->user->role === 'client' && !empty($this->user->client_project_id)) {
            $projectId = (int)$this->user->client_project_id;
        }

        return $this->billingModel->getProjectBillingSummary($projectId, $hourlyRate);
    }

    /**
     * PDF/Baskı Sayfasını Render Eder
     */
    public function renderPrintView(?int $projectId = null, ?float $hourlyRate = null): string
    {
        $billingData = $this->getBillingData($projectId, $hourlyRate);
        return View::render('Billing::billing_report', ['billing' => $billingData], true);
    }
}
