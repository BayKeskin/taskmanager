<?php
/**
 * TaskManager Enterprise Application Entry Point
 * Çekirdek (Core) ve Modül Yönlendiricisi
 */
require_once __DIR__ . '/Core/System.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$page = $_GET['page'] ?? '';

// Çıkış işlemi
if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: " . baseUrl('login'));
    exit;
}

// Giriş yapılmamışsa login ekranına yönlendir
if (empty($_SESSION['user'])) {
    require_once __DIR__ . '/Modules/Team/Views/login.php';
    exit;
}

// Modül bazlı sayfa eşleştirmesi ve aktiflik kontrolü
$pageModuleMap = [
    'calendar'       => 'calendar',
    'billing-report' => 'billing',
    'reports'        => 'reports',
    'docs'           => 'docs',
    'chat'           => 'chat',
    'api-test'       => 'apitester',
    'api-docs'       => 'apitester',
    'agent-rules'    => 'agent-rules',
    'modules'        => 'docs'
];

if (isset($pageModuleMap[$page]) && !\Core\ModuleLoader::isModuleActive($pageModuleMap[$page])) {
    require_once __DIR__ . '/Core/Views/module_disabled.php';
    exit;
}

// Aktif modül yönlendirmesi
switch ($page) {
    case 'calendar':
        require_once __DIR__ . '/Modules/Calendar/Views/calendar.php';
        break;
    case 'billing-report':
        require_once __DIR__ . '/Modules/Billing/Views/billing_report.php';
        break;
    case 'reports':
        require_once __DIR__ . '/Modules/Reports/Views/reports.php';
        break;
    case 'team':
        require_once __DIR__ . '/Modules/Team/Views/team.php';
        break;
    case 'docs':
        require_once __DIR__ . '/Modules/Docs/Views/docs.php';
        break;
    case 'chat':
        require_once __DIR__ . '/Modules/Chat/Views/chat.php';
        break;
    case 'settings':
        require_once __DIR__ . '/Modules/Settings/Views/settings.php';
        break;
    case 'api-test':
        (new \Modules\ApiTester\Controllers\ApiTesterController())->index();
        break;
    case 'api-docs':
        require_once __DIR__ . '/Modules/ApiTester/Views/api_docs.php';
        break;
    case 'agent-rules':
        (new \Modules\AgentRules\Controllers\AgentRulesController())->index();
        break;
    case 'modules':
        require_once __DIR__ . '/Modules/Docs/Views/modules_guide.php';
        break;
    case 'board':
    default:
        require_once __DIR__ . '/Modules/Board/Views/board.php';
        break;
}
