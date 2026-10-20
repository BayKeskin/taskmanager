<?php
namespace Modules\ErrorRadar\Controllers;

use Core\BaseController;

/**
 * AjaxController - AjaxRouter otomatik entegrasyonu (action=report_error veya reportError)
 */
class AjaxController extends BaseController
{
    private ErrorRadarController $controller;

    public function __construct()
    {
        parent::__construct();
        $this->controller = new ErrorRadarController();
    }

    public function reportError(): void
    {
        $this->controller->reportError();
    }
}
