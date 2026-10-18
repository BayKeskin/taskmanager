<?php
/**
 * Legacy ChatController Facade
 * Delegates directly to HMVC Module: Modules\Chat\Controllers\ChatController
 */
require_once __DIR__ . '/Controller.php';

class ChatController extends Controller
{
    private \Modules\Chat\Controllers\ChatController $moduleController;

    public function __construct(?System $system = null)
    {
        parent::__construct($system);
        $this->moduleController = new \Modules\Chat\Controllers\ChatController();
    }

    public function index(?int $initialTargetUserId = null, ?object $currentUser = null): array
    {
        return $this->moduleController->index($initialTargetUserId, $currentUser);
    }
}
