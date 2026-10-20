<?php
namespace Modules\AgentRules\Controllers;

use Core\BaseController;

class AjaxController extends BaseController
{
    private AgentRulesController $controller;

    public function __construct()
    {
        parent::__construct();
        $this->controller = new AgentRulesController();
    }

    public function saveGlobalRules(): void
    {
        $this->controller->saveGlobal();
    }

    public function syncAllProjects(): void
    {
        $this->controller->syncAll();
    }

    public function syncProjectRules(): void
    {
        $this->controller->syncSingle();
    }

    public function saveQaGlobalRules(): void
    {
        $this->controller->saveQaGlobal();
    }
}
