<?php
/**
 * Legacy TeamController Facade
 * Delegates directly to HMVC Module: Modules\Team\Controllers\TeamController
 */
require_once __DIR__ . '/Controller.php';

class TeamController extends Controller
{
    private \Modules\Team\Controllers\TeamController $moduleController;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->moduleController = new \Modules\Team\Controllers\TeamController();
    }

    public function index(?object $currentUser = null): array
    {
        return $this->moduleController->index($currentUser);
    }
}
