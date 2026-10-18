<?php
/**
 * Legacy DocController Facade
 * Delegates directly to HMVC Module: Modules\Docs\Controllers\DocController
 */
require_once __DIR__ . '/Controller.php';

class DocController extends Controller
{
    private \Modules\Docs\Controllers\DocController $moduleController;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->moduleController = new \Modules\Docs\Controllers\DocController();
    }

    public function index(?int $activeProjectId = null, ?int $selectedDocId = null, ?object $currentUser = null): array
    {
        return $this->moduleController->index($activeProjectId, $selectedDocId, $currentUser);
    }
}
