<?php
require_once __DIR__ . '/../../Core/System.php';

// Oturumu tamamen sonlandır
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Yeniden login sayfasına yönlendir
session_start();
header("Location: /taskmanager/login?logout=1");
exit;
