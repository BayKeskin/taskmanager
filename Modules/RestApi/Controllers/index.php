<?php
/**
 * TaskManager Enterprise REST API Router
 */
require_once dirname(__DIR__, 3) . '/Core/System.php';

$controller = new \Modules\RestApi\Controllers\RestApiController();
$controller->handleRequest();
