<?php
require_once __DIR__ . '/../Core/System.php';

$s = new \Modules\SmokeTest\Services\SmokeTestService();
$res = $s->run();
print_r($res);
